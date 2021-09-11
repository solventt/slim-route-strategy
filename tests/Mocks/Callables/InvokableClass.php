<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Tests\Mocks\Callables;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class InvokableClass
{
    public function __invoke(string $test,
                             string $id,
                             self $obj,
                             string $value = 'example'): ResponseInterface
    {
        $params = array_merge(func_get_args(), [$value]);

        $response = (new Response());
        $response->getBody()->write(json_encode($params));
        return $response;
    }
}