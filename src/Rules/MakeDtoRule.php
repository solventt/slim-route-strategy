<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

use Psr\Container\ContainerInterface;
use SlimRouteStrategy\Dto;

/**
 * Converts a data array of POST|PUT|PATCH requests into a Data Transfer Object (DTO)
 */
class MakeDtoRule implements AggregatorRuleInterface
{
    public function __construct(private ContainerInterface $container) {}

    /**
     * @inheritDoc
     */
    public function resolveParameters(
        array $unresolvedParams,
        array $routeParams,
        array $resolvedParams
    ): array
    {
        if (in_array($routeParams['request']->getMethod(), ['POST', 'PATCH', 'PUT'])) {
            $requestData = $routeParams['request']->getParsedBody();

            // The _METHOD field could be present for the Slim built-in MethodOverrideMiddleware
            unset($requestData['_METHOD']);

            foreach ($unresolvedParams as $position => $parameter) {
                if (preg_match('/dto/i', $parameter->name)) {
                    $dto = $this->getDto($parameter->name, $requestData);

                    $resolvedParams[$position] = $dto;
                }
            }
        }
        return $resolvedParams;
    }

    /**
     * You can get your own Dto class by specifying an associative array in the DI Container definitions.
     * E.g. 'dtoFactories' => ['dto' => ProfileUpdateDtoFactory::class] where
     * 'dto' is a callback parameter name and ProfileUpdateDtoFactory::class is the Dto factory.
     *
     * By default, the Dto class filled with the request data will be returned.
     * @param string $parameterName
     * @param array $requestData
     * @return mixed
     */
    private function getDto(string $parameterName, array $requestData)
    {
        if ($this->container->has('dtoFactories')) {
            $dtoFactories = $this->container->get('dtoFactories');
        }

        $factoryClass = $dtoFactories[$parameterName] ?? null;

        if ($factoryClass && $this->container->has($factoryClass)) {
            $dto = $this->container->get($factoryClass)($requestData);
        } else {
            $dto = new Dto();

            foreach ($requestData as $fieldName => $value) {
                $dto->$fieldName = $value;
            }
        }

        return $dto;
    }
}