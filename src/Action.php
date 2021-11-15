<?php
declare(strict_types=1);

namespace Ferror;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface Action
{
    public function __invoke(RequestInterface $request, ResponseInterface $response): ResponseInterface;
}
