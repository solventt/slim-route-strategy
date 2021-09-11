<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;

/**
 * Injects type-hinted callback parameters using the DI container
 */
class TypeHintContainerRule implements AggregatorRuleInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function resolveParameters(array $unresolvedParams,
                                      array $routeParams,
                                      array $resolvedParams): array
    {
        foreach ($unresolvedParams as $position => $parameter) {

            /* @var ReflectionNamedType $parameterType */
            $parameterType = $parameter->getType();

            if (!$parameterType || $parameterType->isBuiltin()) {
                continue;
            }

            //  leave only in ^8.0
            if (!$parameterType instanceof ReflectionNamedType) {
                continue;
            }

            $parameterClass = $parameterType->getName();

            if ($parameterClass === 'self') {
                $parameterClass = $parameter->getDeclaringClass()->getName();
            }

            if ($this->container->has($parameterClass)) {
                $resolvedParams[$position] = $this->container->get($parameterClass);
            }
        }

        return $resolvedParams;
    }
}