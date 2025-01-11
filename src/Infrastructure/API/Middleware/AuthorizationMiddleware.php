<?php

namespace OpenCCK\Infrastructure\API\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use function OpenCCK\dbg;

class AuthorizationMiddleware implements Middleware {
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response {
        if (!$request->getHeader('Authorization')) {
            return $this->getUnauthorizedResponse();
        }

        $header = $request->getHeader('Authorization');
        $data = base64_decode(str_replace('Basic ', '', $header));
        $credentials = explode(':', $data);
        if (
            $credentials[0] !== \OpenCCK\getEnv('SYS_AUTH_LOGIN') ||
            $credentials[1] !== \OpenCCK\getEnv('SYS_AUTH_PASSWORD')
        ) {
            return $this->getUnauthorizedResponse();
        }

        return $requestHandler->handleRequest($request);
    }
    private function getUnauthorizedResponse(): Response {
        return new Response(
            status: 401,
            headers: ['WWW-Authenticate' => 'Basic realm="webmonitor", charset="UTF-8"'],
            body: json_encode(['message' => 'Unauthorized', 'code' => 401])
        );
    }
}
