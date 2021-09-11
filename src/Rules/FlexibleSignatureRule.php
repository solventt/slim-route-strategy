<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

/**
 * Tries to map an associative array of route parameters to the callback parameters names.
 *
 * Assume there is a callback with signature ($request, $response, $id).
 * And there are route parameters ['request' => 'value_1', 'response' => 'value_2', 'id' = '1'].
 * Then callback will receive parameters values ('value_1', 'value_2', '1')
 */
class FlexibleSignatureRule implements AggregatorRuleInterface
{
    /**
     * @inheritDoc
     */
    public function resolveParameters(array $unresolvedParams,
                                      array $routeParams,
                                      array $resolvedParams): array
    {
        foreach ($unresolvedParams as $position => $parameter) {
            if (array_key_exists($parameter->name, $routeParams)) {
                $resolvedParams[$position] = $routeParams[$parameter->name];
            }
        }

        return $resolvedParams;
    }
}