<?php
declare(strict_types=1);

namespace Ferror;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HomeAction implements Action
{
    public function __invoke(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write('Hello World');

        return $response;
    }
}
