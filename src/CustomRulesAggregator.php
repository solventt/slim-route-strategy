<?php

declare(strict_types=1);

namespace SlimRouteStrategy;

use ReflectionParameter;
use SlimRouteStrategy\Rules\AggregatorRuleInterface;
use SlimRouteStrategy\Rules\NullTypeRule;
use SlimRouteStrategy\Rules\FlexibleSignatureRule;
use SlimRouteStrategy\Rules\MakeDtoRule;
use SlimRouteStrategy\Rules\TypeHintContainerRule;
use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Slim\Interfaces\InvocationStrategyInterface;
use TypeError;

class CustomRulesAggregator implements InvocationStrategyInterface
{
    private ContainerInterface $container;

    private array $rules;

    /**
     * @param array $rules
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container, array $rules = [])
    {
        foreach ($rules as $rule) {
            if (!is_string($rule) || !class_exists($rule)) {
                throw new InvalidArgumentException('Aggregator rule must be declared as an existent class string');
            }
        }

        $this->rules = !$rules ? $this->setDefaultRules() : $rules;

        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(callable $callable,
                             ServerRequestInterface $request,
                             ResponseInterface $response,
                             array $routeArguments): ResponseInterface
    {
        $parameters = $this->prepareParams($request, $response, $routeArguments);

        $callableReflection = $this->createCallableReflection($callable);

        $args = $this->resolveCallbackParameters($callableReflection, $parameters);

        ksort($args);

        $this->checkParamsResolving($callableReflection, $args);

        return $callable(...$args);
    }

    /**
     * Prepares the necessary array of parameters
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $routeArguments
     * @return array
     */
    private function prepareParams(ServerRequestInterface $request,
                                   ResponseInterface $response,
                                   array $routeArguments): array
    {
        $parameters = ['request' => $request, 'response' => $response] + $routeArguments;
        $parameters += $request->getAttributes();

        // The RouteContext constants are not used below because the class is absent in Slim version 3
        unset($parameters['__route__'],
              $parameters['__routeParser__'],
              $parameters['__routingResults__'],
              $parameters['__basePath__']);

        return $parameters;
    }

    /**
     * Checks that all callback parameters are resolved
     * @param ReflectionFunctionAbstract $callableReflection
     * @param array $args
     * @throws NotEnoughParametersException
     */
    private function checkParamsResolving(ReflectionFunctionAbstract $callableReflection, array $args): void
    {
        $diff = array_diff_key($callableReflection->getParameters(), $args);

        if ($diff) {
            $parameters = [];

            /** @var ReflectionParameter $param */
            foreach ($diff as $param) {
                if (!$param->isVariadic() && !$param->isDefaultValueAvailable()) {
                    $type = $param->getType() ? $param->getType()->getName() . ' ' : '';
                    $parameters[] = $type . '$' . $param->name;
                }
            }

            if ($parameters) {
                throw new NotEnoughParametersException(sprintf(
                    'Unable to invoke the callable because no value was given for %s (%s)',
                    count($parameters) > 1 ? 'parameters' : 'parameter',
                    implode(', ', $parameters)
                ));
            }
        }
    }

    /**
     * Creates a reflection object from a route callable
     * @param callable $callable
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     */
    private function createCallableReflection(callable $callable): ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure || is_string($callable)) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            return new ReflectionMethod(...$callable);
        }

        return new ReflectionMethod($callable, '__invoke');
    }

    /**
     * Resolves route callback parameters traversing resolving rules
     * @param ReflectionFunctionAbstract $callableReflection
     * @param array $routeParams request/response objects, route placeholders values, request attributes
     * @return array
     */
    private function resolveCallbackParameters(ReflectionFunctionAbstract $callableReflection,
                                               array $routeParams): array
    {
        $resolvedParams = [];

        $unresolvedParams = $callableReflection->getParameters();

        /** @var class-string $classRule */
        foreach ($this->rules as $classRule) {

            if ($this->container->has($classRule)) {
                /* @var AggregatorRuleInterface $rule */
                $rule = $this->container->get($classRule);

                // if the DI Container has no autowiring
            } else {
                $except = [MakeDtoRule::class, TypeHintContainerRule::class];
                $rule = !in_array($classRule, $except) ? new $classRule() : new $classRule($this->container);
            }

            if (!$rule instanceof AggregatorRuleInterface) {
                throw new TypeError(sprintf('The %s rule must implement AggregatorRuleInterface', $classRule));
            }

            $resolvedParams = $rule->resolveParameters($unresolvedParams, $routeParams, $resolvedParams);

            $unresolvedParams = array_diff_key($unresolvedParams, $resolvedParams);

            if (empty($unresolvedParams)) {
                return $resolvedParams;
            }
        }

        return $resolvedParams;
    }

    /**
     * Sets default rules if they were not given in $this constructor
     * @return string[]
     */
    private function setDefaultRules(): array
    {
        return [
            FlexibleSignatureRule::class,
            TypeHintContainerRule::class,
            NullTypeRule::class
        ];
    }
}