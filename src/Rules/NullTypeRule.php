<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

/**
 * If a callback parameter does not have a default value,
 * it checks presence of the 'null' parameter type and (if successful) takes it for resolving
 */
class NullTypeRule implements AggregatorRuleInterface
{
    /**
     * @inheritDoc
     */
    public function resolveParameters(array $unresolvedParams,
                                      array $routeParams,
                                      array $resolvedParams): array
    {
        foreach ($unresolvedParams as $position => $parameter) {

            if (!$parameter->isDefaultValueAvailable()) {
                $parameterType = $parameter->getType();
                if ($parameterType && $parameterType->allowsNull()) {
                    $resolvedParams[$position] = null;
                }
            }
        }

        return $resolvedParams;
    }
}