<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Tests;

use SlimRouteStrategy\CustomRulesAggregator;
use SlimRouteStrategy\Dto;
use SlimRouteStrategy\Rules\FlexibleSignatureRule;
use SlimRouteStrategy\Rules\IdIntegerTypeRule;
use SlimRouteStrategy\Rules\MakeDtoRule;
use SlimRouteStrategy\NotEnoughParametersException;
use SlimRouteStrategy\Rules\TypeHintContainerRule;
use SlimRouteStrategy\Tests\Mocks\Callables\ArrayCallable;
use SlimRouteStrategy\Tests\Mocks\Callables\InvokableClass;
use SlimRouteStrategy\Tests\Mocks\Dto\UserUpdateDto;
use SlimRouteStrategy\Tests\Mocks\Dto\UserUpdateDtoFactory;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use stdClass;

class CustomRulesAggregatorTest extends TestCase
{
    private ContainerInterface $container;
    private ServerRequestInterface $request;
    private ResponseInterface $response;
    private array $params;
    private array $jsonArgumentsStringsArray;

    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->enableOriginalClone()
            ->onlyMethods(['getAttributes', 'getMethod', 'getParsedBody'])
            ->getMock();

        $this->request->method('getAttributes')
            ->willReturn([
                'test' => 'someValue',
                '__route__' => 'route',
            ]);

        $this->request->method('getMethod')
            ->willReturn('PATCH');

        $this->request->method('getParsedBody')
            ->willReturn([
                'name' => 'Alex',
                'email' => 'email@email.com',
                'date' => '21 august 1970',
                'phoneType' => '3',
                'isActive' => '1'
            ]);

        $this->response = new Response();
        $this->params = ['id' => '5'];

        $this->container = new Container();
    }

    /**
     * Used in testDefaultRules for creating an array with callbacks work verification strings
     */
    private function generateJsonArgumentsString()
    {
        $argumentsValues = [
            'closure' => [$this->request, $this->response, '5', 'someValue', new \stdClass(), null, 5],
            'usualFunc' => [$this->response, 'someValue', true],
            'arrayCallable' => [new \stdClass(), null, $this->request],
            'invokable' => ['someValue', '5', new InvokableClass(),'example'],
        ];

        foreach ($argumentsValues as $callableType => $arguments) {
            $this->jsonArgumentsStringsArray[$callableType] = json_encode($arguments);
        }
    }

    public function callables(): array
    {
        $closure = function (ServerRequestInterface $request,
                             ResponseInterface $response,
                             string $id,
                             string $test,
                             \stdClass $obj,
                             null|string $name,
                             ?int $count = 5): ResponseInterface {

            $params = array_merge(func_get_args(), [$count]);

            $response->getBody()->write(json_encode($params));
            return $response;
        };

        return [
            'closure' => [$closure, 'closure'],
            'usualFunc' => ['usualFunc', 'usualFunc'],
            'arrayCallable' => [[ArrayCallable::class, 'test'], 'arrayCallable'],
            'invokable' => [new InvokableClass(), 'invokable'],
        ];
    }

    /**
     * @dataProvider callables
     * @param callable $callable
     * @param string $callableType
     */
    public function testDefaultRules(callable $callable, string $callableType)
    {
        $strategy = new CustomRulesAggregator($this->container);

        $result = $strategy($callable, $this->request, $this->response, $this->params);

        $this->generateJsonArgumentsString();

        self::assertInstanceOf(ResponseInterface::class, $result);

        self::assertJsonStringEqualsJsonString($this->jsonArgumentsStringsArray[$callableType],
                                               $result->getBody()->__toString());
    }

    public function testIdIntegerTypeRule()
    {
        $strategy = new CustomRulesAggregator($this->container, [IdIntegerTypeRule::class]);

        $callable = function (int $id): ResponseInterface {
            $response = (new Response());
            $response->getBody()->write(gettype($id));
            return $response;
        };

        self::assertIsString($this->params['id']);

        $result = $strategy($callable, $this->request, $this->response, $this->params);

        self::assertEquals('integer', $result->getBody()->__toString());
    }

    public function testMakingDtoRuleWithoutDtoFactory()
    {
        $strategy = new CustomRulesAggregator($this->container, [MakeDtoRule::class]);

        $callable = function (Dto $dto): ResponseInterface {
            $response = (new Response());

            $response->getBody()->write(get_class($dto) . ',' . $dto->name . ',' . $dto->email);
            return $response;
        };

        $result = $strategy($callable, $this->request, $this->response, $this->params);

        self::assertEquals(Dto::class . ',Alex,email@email.com', $result->getBody()->__toString());
    }

    public function testMakingDtoRuleWithDtoFactory()
    {
        $this->container->set('dtoFactories', ['dto' => UserUpdateDtoFactory::class]);

        $strategy = new CustomRulesAggregator($this->container, [MakeDtoRule::class]);

        $callable = function (UserUpdateDto $dto): ResponseInterface {
            $response = (new Response());

            $str = get_class($dto) . ',' . get_class($dto->date) . ',' . gettype($dto->phoneType) . ',' . gettype($dto->isActive);

            $response->getBody()->write($str);
            return $response;
        };

        $result = $strategy($callable, $this->request, $this->response, $this->params);

        self::assertEquals(UserUpdateDto::class . ',DateTime,integer,boolean', $result->getBody()->__toString());
    }

    public function testProcessingOfParameters()
    {
        $strategy = new CustomRulesAggregator($this->container);

        self::assertArrayHasKey('__route__', $this->request->getAttributes());

        $class = new ReflectionClass($strategy);
        $method = $class->getMethod('prepareParams');
        $method->setAccessible(true);

        $result = $method->invoke($strategy, $this->request, $this->response, $this->params);

        self::assertIsArray($result);
        self::assertInstanceOf(ServerRequestInterface::class, $result['request']);
        self::assertInstanceOf(ResponseInterface::class, $result['response']);
        self::assertEquals('5', $result['id']);
        self::assertEquals('someValue', $result['test']);

        self::assertArrayNotHasKey('__route__', $result);
    }

    public function testIncorrectRuleFormat()
    {
        self::expectExceptionMessage('Aggregator rule must be declared as an existent class string');

        new CustomRulesAggregator($this->container, [new FlexibleSignatureRule()]);
    }

    public function testNotExistentRuleClass()
    {
        self::expectExceptionMessage('Aggregator rule must be declared as an existent class string');

        new CustomRulesAggregator($this->container, ['NonExistentRuleClass']);
    }

    public function testNotCallbackType()
    {
        self::expectError();

        $strategy = new CustomRulesAggregator($this->container);
        $strategy('callable', $this->request, $this->response, $this->params);
    }

    public function testNotEnoughParameters()
    {
        $strategy = new CustomRulesAggregator($this->container);

        $callable = fn(ResponseInterface $response, string $package): ResponseInterface => $response;

        $this->expectException(NotEnoughParametersException::class);

        $strategy($callable, $this->request, $this->response, $this->params);
    }

    public function testRuleIsNotInstanceOfAggregatorRuleInterface()
    {
        $strategy = new CustomRulesAggregator($this->container, [stdClass::class]);

        $this->expectError();

        $strategy(new InvokableClass(), $this->request, $this->response, $this->params);
    }

    public function testUnionTypesInTypeHintContainerRule()
    {
        $closure = function (string $id, stdClass|InvokableClass $class) {};

        $strategy = new CustomRulesAggregator($this->container, [TypeHintContainerRule::class]);

        $this->expectException(NotEnoughParametersException::class);

        $strategy($closure, $this->request, $this->response, $this->params);
    }
}