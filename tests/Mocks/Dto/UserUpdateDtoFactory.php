<?php

declare(strict_types=1);

namespace SlimRouteStrategy\Tests\Mocks\Dto;

class UserUpdateDtoFactory
{
    public function __invoke(array $requestData): UserUpdateDto
    {
        $dto = new UserUpdateDto();

        foreach ($requestData as $field => $value) {

            switch ($field) {
                case 'phoneType':
                    $value = (int) $value;
                    break;
                case 'date':
                    $value = new \DateTime($value);
                    break;
                case 'isActive':
                    $value = (bool) $value;
            }

            $dto->$field = $value;
        }
        return $dto;
    }
}