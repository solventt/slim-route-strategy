<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Tests\Mocks\Callables;

use Psr\Http\Message\ResponseInterface;

class ArrayCallable
{
    public static function test(\stdClass $obj,
                                ?int $count,
                                ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode(func_get_args()));
        return $response;
    }
}