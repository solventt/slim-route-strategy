### Table of contents
1) [Requirements](#requirements)
2) [Installing](#installing)
3) [Flexible controller signature](#flexible-controller-signature)
4) [Features](#features)
5) [Resolving DTO](#resolving-dto)
6) [Use cases](#use-cases)
7) [Writing custom rules](#writing-custom-rules)

Package is an implementation of a route invocation strategy for the Slim microframework. It allows to flexibly set up resolving of your controller parameters. About the invocation strategy you can read in the Slim [docs](https://www.slimframework.com/docs/v4/objects/routing.html#route-strategies).

### Requirements

- PHP ^7.4 or ^8.0
- Slim microframework version  3+ or 4+
- any DI container. But if it has no autowiring, ```TypeHintContainerRule``` and ```MakeDtoRule``` will not affect the resolving of controller parameters.

### Installing
```
// php ^7.4
composer require solvent13/slim-route-strategy ^0.1

// php ^8.0
composer require solvent13/slim-route-strategy ^1.0
```

### Flexible controller signature

By default, Slim controllers have a strict signature: ```$request```, ```$response```, ```$args``` 

And so you can't omit any of these parameters even if one is not needed. It is called the ```RequestResponse``` strategy.

But with this package:
- you may specify any parameters you need and even an empty controller signature
- the order of the parameters doesn't matter
- services will be injected by type-hint
- in addition to the route placeholders you also can receive request attributes and Data Transfer Objects (instead of the POST/PUT/PATCH arrays) in your controller parameters
- incoming ```$id``` parameter will have integer type instead of default string type (optional)
- you can add your own parameters resolving functionality, for example, instead of the ```$id``` parameter you may receive some entity (User)

### Features

The route ```CustomRulesAggregator``` strategy consist of the following rules:

1) **IdIntegerTypeRule** (optional) - casts string type of the 'id' route parameter (if exists) to integer type. It's especially conveniently while using declare(strict_types=1)
```
$app->get('/profile/{id:\d+}', [ProfileController::class, 'show']);

...

public function show(int $id): Response
{
   // incoming $id has integer type instead of default's string
}
```
**NOTE**: the name of the controller parameter and the route placeholder MUST be ```id```.

2) **FlexibleSignatureRule** - tries to map an associative array of route parameters to the controller parameters names.

Assume there is the controller method:
```
public function show($request, $response, $id) {}
```
And there are the route parameters:
```
[
   'request' => 'value_1', 
   'response' => 'value_2', 
   'id' = '1'
]
```
Then controller method will receive next parameters values:
```
public function show($request, $response, $id)
{
  echo $request;   // 'value_1'
  echo $response;  // 'value_2'
  echo $id;        // '1' - string, because the IdIntegerTypeRule is off
}
```
**NOTE:** the names of the controller request/response parameters MUST be ```request``` and ```response``` accordingly.
3) **TypeHintContainerRule** - injects type-hinted controller parameters using the DI container. But the union types will be ignored.
```
public function show(Twig $twig, self $surrentClass) 
{
   // The Twig and declaring class instances will be automatically resolved
}
```
4) **NullTypeRule** - if a controller parameter does not have a default value, it checks presence of the 'null' parameter type and (if successful) take it for resolving:
```
public function show(?string $name, ?int $count = 5) 
{
   var_dump($name);   // null
   echo $count;       // 5
}
```
5) **MakeDtoRule** - read the next section.

By default, only ```FlexibleSignatureRule```, ```TypeHintContainerRule``` and ```NullTypeRule``` are active.

Also, you can add your own rules.
### Resolving DTO
```MakeDtoRule``` converts a data array of POST|PUT|PATCH requests into a Data Transfer Object (DTO)
```
public function update(Dto $dto, int $id)
{
   // do something with $dto
}
```
By default, it will be created the built-in Dto class filled with the request data. But you can define your own DTO class and your own logic for processing the data and filling the object with it, using factories.

**Example**

Definition for the DI Container:
```
return [
    'dtoFactories' => [
         
         // key - is a parameter name of a controller method
         // value - a corresponding DTO factory class
         
         'dto' => UserUpdateDtoFactory::class 
    ]
];
```
Factory logic:
```
class UserUpdateDtoFactory
{
   public function __invoke(array $requestData): UserUpdateDto
   {
      $dto = new UserUpdateDto();

      foreach ($requestData as $field => $value) {

         $value = match ($field) {
                'phoneType' => (int) $value,
                'date' => new \DateTime($value),
                'isActive' => (bool) $value,
                 default => $value
            };

         $dto->$field = $value;
      }
      
      return $dto;
   }
}
```
And the controller method:
```
public function update(UserUpdateDto $dto)
{
   // do something with $dto
}
```
**REMEMBER:**
1) Name of the parameter must contain a 'dto' substring. For example: '$userUpdateDto', '$dto', 'myDto', 'loginDto' and so forth.
2) You need to specify a parameter name in the DI Container definition as an array key. The value of the array - a corresponding DTO factory class.
3) The DI container definition must be named as 'dtoFactories' (see the example above).

### Use cases
For Slim version ^4.0, ```index.php```:
```
<?php

use DI\Container;
use Slim\Factory\AppFactory;
use SlimRouteStrategy\CustomRulesAggregator;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();

$app = AppFactory::createFromContainer($container);

$strategy = new CustomRulesAggregator($container);

$app->getRouteCollector()->setDefaultInvocationStrategy($strategy);

$app->get('/hello/{name}', function ($response, $name) {
    $response->getBody()->write($name);

    return $response;
});

$app->run();
```

For Slim version ^3.0, ```index.php```:
```
<?php

use Slim\App;
use Slim\Container;
use SlimRouteStrategy\CustomRulesAggregator;

require __DIR__ . '/vendor/autoload.php';

$container = new Container();

$container['foundHandler'] = fn () => new CustomRulesAggregator($container);

$app = new App($container);

$app->get('/hello/{name}', function ($response, $name) {
    $response->getBody()->write($name);

    return $response;
});

$app->run();
```
**About the strategy rules**

If you don't provide any rules to the rout strategy constructor, only ``FlexibleSignatureRule``, ``TypeHintContainerRule`` and ``NullTypeRule`` will be enabled by default.

For example, you want to add ```IdIntegerTypeRule``` and ```MakeDtoRule```, then you should define all necessary rules explicitly:
```
...

$strategyRules = [

    IdIntegerTypeRule::class,
    FlexibleSignatureRule::class,
    MakeDtoRule::class,
    TypeHintContainerRule::class,
    NullTypeRule::class
    
];

$strategy = new CustomRulesAggregator($container, $strategyRules);

...
```
Or if you want to add only a rule:
```
...

$strategy = new CustomRulesAggregator($container, [FlexibleSignatureRule::class]);

...
```

REMEMBER:
- the rules must be specified as existent class strings
- the rules order matters. E.g. if you define ```IdIntegerTypeRule``` after ```FlexibleSignatureRule```. Then ```IdIntegerTypeRule``` will have no effect - the type of the ```id``` will be string instead of integer. 

**The example above shows the correct order of the rules**.

### Writing custom rules
Your custom rule must implement ```AggregatorRuleInterface```. 

Let's look at the simple example. Suppose you want the controller method to receive the User entity as an argument. So the route is:
```
$app->get('/profile/{user:\d+}', [ProfileController::class, 'show']);
```
The controller method is:
```
public function show(User $user){}
```
And you wrote your custom ```FindUserEntityRule```:
```
class FindUserEntityRule implements AggregatorRuleInterface
{
    public function __construct(private UserRepository $users){}

    /**
     * @param ReflectionParameter[]  $unresolvedParams parameters that have not yet been resolved
     * @param array $routeParams     request/response objects, route placeholders values, request attributes
     * @param array $resolvedParams  parameters resolved by previous rule (indexed by parameter position)
     * @return array                 parameters resolved by this + by previous rule
     */
    public function resolveParameters(array $unresolvedParams,
                                      array $routeParams,
                                      array $resolvedParams): array
    {
        foreach ($unresolvedParams as $position => $parameter) {

            if ($parameter->name === 'user' && $this->hasAppropriateType($parameter)) {
                if (array_key_exists($parameter->name, $routeParams)) {

                    $userId = (int) $routeParams[$parameter->name];
                    $user = $this->users->findOne($userId);

                    $resolvedParams[$position] = $user;
                }
            }
        }

        return $resolvedParams;
    }

    private function hasAppropriateType(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return !$type instanceof ReflectionUnionType && $type->getName() === User::class;
    }
}
```