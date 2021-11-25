<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Injects type-hinted callback parameters using the DI container
 */
class TypeHintContainerRule implements AggregatorRuleInterface
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
        foreach ($unresolvedParams as $position => $parameter) {
            $parameterType = $parameter->getType();

            if (!$parameterType || $parameterType instanceof ReflectionUnionType) {
                continue;
            }

            /** @var ReflectionNamedType $parameterType */
            if ($parameterType->isBuiltin()) {
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