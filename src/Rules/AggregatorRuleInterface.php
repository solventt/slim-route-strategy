<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Rules;

use ReflectionParameter;

interface AggregatorRuleInterface
{
    /**
     * @param ReflectionParameter[] $unresolvedParams parameters that have not yet been resolved
     * @param array  $routeParams request/response objects, route placeholders values, request attributes
     * @param array  $resolvedParams parameters resolved by previous rule (indexed by parameter position)
     * @return array parameters resolved by this + by previous rule
     */
    public function resolveParameters(
        array $unresolvedParams,
        array $routeParams,
        array $resolvedParams): array;
}