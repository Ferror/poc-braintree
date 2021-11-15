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
        $response
            ->withHeader('Content-Type', 'application/json')
//            ->withHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN'])
//            ->withHeader('Access-Control-Request-Method', 'GET,OPTIONS')
            ->getBody()->write(json_encode(['token' => $this->gateway->clientToken()->generate()], JSON_THROW_ON_ERROR));

        return $response;
    }
}
