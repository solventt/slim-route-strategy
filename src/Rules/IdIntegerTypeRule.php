<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

/**
 * Casts string type of the 'id' route parameter (if exists) to integer type.
 * It's especially conveniently while using 'declare(strict_types=1)'
 */
class IdIntegerTypeRule implements AggregatorRuleInterface
{
    /**
     * @inheritDoc
     */
    public function resolveParameters(
        array $unresolvedParams,
        array $routeParams,
        array $resolvedParams
    ): array
    {
        if (isset($routeParams['id'])) {
            $routeParams['id'] = (int) $routeParams['id'];

            foreach ($unresolvedParams as $position => $parameter) {
                if ($parameter->name === 'id') {
                    $resolvedParams[$position] = $routeParams[$parameter->name];
                }
            }
        }

        return $resolvedParams;
    }
}