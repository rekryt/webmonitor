<?php

namespace OpenCCK\App\Service;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Response;

use Monolog\Logger;
use OpenCCK\Infrastructure\API\App;

use Exception;
use Throwable;

use function OpenCCK\getEnv;

final class TelegramService {
    protected HttpClient $httpClient;
    private ?Logger $logger;
    private string $baseURL = 'https://api.telegram.org/';

    /**
     * @throws Throwable
     */
    public function __construct(private ?string $token = null) {
        $this->token = $token ?? (getEnv('TELEGRAM_BOT_TOKEN') ?? '');

        $this->httpClient = (new HttpClientBuilder())->build();

        $this->logger = App::getLogger();
    }

    private function implodeKeys(array $array): array {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->implodeKeys($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param string $url
     * @param array $params
     * @param string $method
     * @return Response
     * @throws BufferException
     * @throws HttpException
     * @throws StreamException
     * @throws Exception
     */
    protected function httpRequest(string $url, array $params = [], string $method = 'GET'): mixed {
        $this->logger->notice($url);
        $request = new Client\Request($url, $method);
        $request->setInactivityTimeout(60);
        $request->setTransferTimeout(60);
        $request->setTlsHandshakeTimeout(60);
        $request->setTcpConnectTimeout(60);

        if ($method === 'POST') {
            $request->setHeader('Content-type', 'application/json');
            $request->setBody(json_encode($params));
            //            $form = new Client\Form();
            //            foreach ($params as $key => $value) {
            //                $form->addField($key, $value);
            //            }
            //            $request->setBody($form);
        } else {
            $request->setQueryParameters($params);
        }

        $response = $this->httpClient->request($request);
        $data = $response->getBody()->buffer();
        $responseData = json_decode($data);
        if (is_null($responseData)) {
            return $data;
        }

        $this->logger->notice(json_encode($responseData));

        //        if (!$responseData->success) {
        //            throw new Exception(
        //                $response->getReason() . ': ' . ($responseData->error->message ?? $responseData->message),
        //                $response->getStatus()
        //            );
        //        }
        //
        //        $this->logger->notice($url, [
        //            $response->getHeader('X-RateLimit-Remaining'),
        //            $response->getHeader('X-RateLimit-Limit'),
        //            $params,
        //        ]);

        return $responseData;
    }

    /**
     * @throws BufferException
     * @throws StreamException
     * @throws HttpException
     */
    public function sendMessage(array $data = []): bool {
        $response = $this->httpRequest($this->baseURL . '/bot' . $this->token . '/sendMessage', $data, 'POST');

        $this->logger->notice('sendMessage', [json_encode($response)]);

        return true;
    }

    public function sendPhoto(array $data = []): mixed {
        $response = $this->httpRequest($this->baseURL . '/bot' . $this->token . '/sendPhoto', $data, 'POST');
        $this->logger->notice('sendPhoto', [json_encode($response)]);
        return $response;
    }

    static function getOptions(
        ?int $chat_id = null,
        ?string $parse_mode = null,
        ?bool $disable_web_page_preview = null,
        ?bool $disable_notification = null,
        ?int $reply_to_message_id = null,
        ?array $reply_markup = null
    ): array {
        return array_merge(
            $chat_id ? ['chat_id' => $chat_id] : [],
            $parse_mode ? ['parse_mode' => $parse_mode] : [],
            $disable_web_page_preview ? ['disable_web_page_preview' => $disable_web_page_preview] : [],
            $disable_notification ? ['disable_notification' => $disable_notification] : [],
            $reply_to_message_id ? ['reply_to_message_id' => $reply_to_message_id] : [],
            $reply_markup ? ['reply_markup' => $reply_markup] : []
        );
    }

    static function getReplyMarkup(
        ?bool $resize_keyboard = null,
        ?bool $one_time_keyboard = null,
        ?bool $selective = null,
        ?array $keyboard = null,
        ?array $inline_keyboard = null,
        ?bool $force_reply = null
    ): array {
        return array_merge(
            $resize_keyboard ? ['resize_keyboard' => $resize_keyboard] : [],
            $one_time_keyboard ? ['one_time_keyboard' => $one_time_keyboard] : [],
            $selective ? ['selective' => $selective] : [],
            $keyboard ? ['keyboard' => $keyboard] : [],
            $inline_keyboard ? ['inline_keyboard' => $inline_keyboard] : [],
            $force_reply ? ['force_reply' => $force_reply] : []
        );
    }
}
