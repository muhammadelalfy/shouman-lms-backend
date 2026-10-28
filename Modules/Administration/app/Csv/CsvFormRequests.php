<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;

final class CsvFormRequests
{
    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $routeParams
     * @return T
     */
    public static function validate(string $class, User $actor, array $input, array $routeParams = [], ?int $rowNumber = null): FormRequest
    {
        $form = $class::create('/csv-validation', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($input, JSON_THROW_ON_ERROR));
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->setUserResolver(fn (): User => $actor);
        $route = new Route('POST', 'csv-validation', fn (): null => null);
        $route->bind($form);
        foreach ($routeParams as $name => $value) {
            $route->setParameter($name, $value);
        }
        $form->setRouteResolver(fn (): Route => $route);
        try {
            $form->validateResolved();
        } catch (ValidationException $exception) {
            if ($rowNumber === null) {
                throw $exception;
            }
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['rows.'.$rowNumber.'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return $form;
    }
}
