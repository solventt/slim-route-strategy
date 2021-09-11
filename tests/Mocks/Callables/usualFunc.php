<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;

function usualFunc(ResponseInterface $response,
                   string $test,
                   bool $check = true,
                   ...$variadic): ResponseInterface
{
    $params = array_merge(func_get_args(), [$check]);

    $response->getBody()->write(json_encode($params));
    $response->getBody()->rewind();

    return $response;
}