<?php
declare(strict_types=1);

namespace Ferror;

use Braintree\Gateway;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CreateBraintreeTokenAction implements Action
{
    public function __construct(
        private Gateway $gateway
    )
    {
    }

    public function __invoke(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->getBody()->write(json_encode(['token' => $this->gateway->clientToken()->generate()], JSON_THROW_ON_ERROR));

        return $response;
    }
}
