<?php

namespace OpenCCK\Domain\Entity;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Amp\Socket\ConnectContext;
use Amp\Http\Client;

use OpenCCK\App\Service\TelegramService;
use OpenCCK\Infrastructure\API\App;

use Revolt\EventLoop;
use function Amp\Socket\connect;

final class Check {
    public string $value;
    private HttpClient $httpClient;
    private int $tries = 0;
    private ?object $lastMessage = null;

    /**
     * @param string $site Name of portal
     * @param string $name Name of check
     * @param string $type Type of portal check
     * @param array $params Parameters of check
     * @throws ProcessException
     */
    public function __construct(
        public string $site,
        public string $name,
        public string $type,
        private readonly array $params = ['timeout' => 5]
    ) {
        $this->httpClient = (new HttpClientBuilder())->build();
        $this->reload();
    }
    /**
     * @return void
     * @throws ProcessException
     */
    public function reload(): void {
        $startTime = microtime(true); // Начало замера времени
        $newValue = '+Inf';
        try {
            switch ($this->type) {
                case 'http':
                    $request = new Client\Request($this->params['url'], 'GET');
                    $request->setInactivityTimeout(5);
                    $request->setTransferTimeout(5);
                    $request->setTlsHandshakeTimeout(15);
                    $request->setTcpConnectTimeout(5);
                    $response = $this->httpClient->request($request);
                    $data = $response->getBody()->buffer();
                    //$isHTML =
                    //    str_contains($data, '<!doctype') ||
                    //    str_contains($data, '<html') ||
                    //    str_contains($data, '<!DOCTYPE') ||
                    //    str_contains($data, '<HTML');

                    $newValue =
                        $response->getStatus() == '200' && !empty($data)
                            ? (microtime(true) - $startTime) * 1000
                            : '+Inf';

                    break;
                case 'ping':
                    if (\DIRECTORY_SEPARATOR === '\\') {
                        $command = "ping -n 5 {$this->params['host']}";

                        $process = Process::start($command);
                        $process->join();
                        $result = explode("\n", $process->getStdout()->read());
                        $result = array_filter($result, fn(string $item) => count(explode(' ', $item)) == 7);
                        $result = array_map(fn(string $item) => explode(' ', $item), $result);
                        $result = array_map(fn(array $item) => preg_replace('/[^,.0-9]/', '', $item[5]), $result);
                    } else {
                        $command = "ping -c 5 {$this->params['host']}";

                        $process = Process::start($command);
                        $process->join();
                        $result = explode("\n", $process->getStdout()->read());
                        $result = array_filter($result, fn(string $item) => str_ends_with($item, 'ms'));
                        $result = array_map(fn(string $item) => explode(' ', $item), $result);
                        $result = array_map(
                            fn(array $item) => preg_replace('/[^,.0-9]/', '', $item[count($item) - 2]),
                            $result
                        );
                    }

                    if (count($result)) {
                        $newValue = (float) array_sum($result) / count($result);
                    }
                    break;
                case 'tcp':
                    $connectContext = (new ConnectContext())->withConnectTimeout(5);
                    $socket = connect($this->params['host'] . ':' . $this->params['port'], $connectContext);
                    $newValue = (microtime(true) - $startTime) * 1000;

                    $socket->close();

                    break;
            }
        } catch (\Throwable $e) {
            App::getLogger()->error($e->getMessage(), [$this]);
        }

        // Alert
        try {
            $telegram = new TelegramService();
            $chat_id = \OpenCCK\getEnv('TELEGRAM_BOT_CHAT_ID');
            $triesCount = \OpenCCK\getEnv('SYS_CHECK_MAX_TRIES') ?? 3;

            if (!is_null($this->lastMessage) && $newValue !== '+Inf') {
                $telegram->deleteMessage($chat_id, $this->lastMessage->message_id);
                $this->lastMessage = null;
            }

            if (
                isset($this->value) &&
                \OpenCCK\getEnv('TELEGRAM_BOT_CHAT_ID') &&
                (($this->value !== '+Inf' && $newValue === '+Inf') || ($this->value === '+Inf' && $newValue !== '+Inf'))
            ) {
                $text =
                    $newValue === '+Inf'
                        ? ($this->tries == $triesCount
                            ? \OpenCCK\getEnv('TELEGRAM_BOT_MESSAGE_ALERT')
                            : \OpenCCK\getEnv('TELEGRAM_BOT_MESSAGE_WARNING'))
                        : \OpenCCK\getEnv('TELEGRAM_BOT_MESSAGE_SUCCESS');
                foreach (['site', 'name', 'type', 'value'] as $pk) {
                    $val = $this->{$pk};
                    if ($pk == 'value') {
                        $val = round((float) $newValue, 0);
                    }
                    $text = str_replace('{' . $pk . '}', $val, $text);
                }

                if ($this->tries < $triesCount && $newValue === '+Inf') {
                    $this->tries++;
                    $newValue = $this->value;
                    App::getLogger()->notice('Pending[' . $this->tries . '/' . $triesCount . '] ' . $this->type, [
                        $this->value,
                        $this->name,
                        $this->params,
                    ]);
                } else {
                    $this->tries = 0;
                    if (!is_null($this->lastMessage) && $newValue === '+Inf') {
                        $telegram->deleteMessage($chat_id, $this->lastMessage->message_id);
                        $this->lastMessage = null;
                    }
                }

                if ($this->tries <= 1) {
                    $message = $telegram->sendMessage(
                        array_merge(
                            ['text' => $text, 'silent' => $this->tries > 0],
                            TelegramService::getOptions(
                                chat_id: $chat_id,
                                parse_mode: 'html',
                                disable_notification: $this->tries > 0
                            )
                        )
                    );
                    $this->lastMessage = $this->tries > 0 ? $message : null;
                }
            } else {
                $this->tries = 0;
            }
        } catch (\Throwable $e) {
            App::getLogger()->error($e->getMessage(), [$this]);
        }

        $this->value = $newValue;
        App::getLogger()->notice('Reloaded ' . $this->type, [$this->value, $this->name, $this->params]);

        EventLoop::delay($this->params['timeout'], function () {
            $this->reload();
        });
    }
}
