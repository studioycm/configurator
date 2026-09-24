<?php

namespace App\Filament\Resources\Configurators\Schemas;

use Closure;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ConfiguratorFormErrors
{
    public static function run(Closure $save, ?Schema $schema = null): mixed
    {
        try {
            return $save();
        } catch (Throwable $exception) {
            self::rethrow($exception, $schema);
        }
    }

    public static function rethrow(Throwable $exception, ?Schema $schema, ?string $removePrefix = null): never
    {
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || $exception instanceof HttpExceptionInterface) {
            throw $exception;
        }
        if (! $exception instanceof ValidationException) {
            report($exception);
            $exception = ValidationException::withMessages(['save' => 'Changes could not be saved. Your draft was kept. Please try again.']);
        }
        if ($schema === null) {
            throw $exception;
        }
        $state = $schema->getRawState();
        $state = $state instanceof Arrayable ? $state->toArray() : $state;
        $errors = [];
        foreach ($exception->errors() as $path => $messages) {
            $path = $removePrefix === null ? $path : preg_replace($removePrefix, '', $path);
            $cursor = $state;
            $segments = [];
            foreach (explode('.', $path) as $segment) {
                if (is_array($cursor) && ctype_digit($segment) && ! array_key_exists($segment, $cursor) && ! array_is_list($cursor)) {
                    $segment = (string) (array_keys($cursor)[(int) $segment] ?? $segment);
                }
                $segments[] = $segment;
                $cursor = is_array($cursor) ? ($cursor[$segment] ?? null) : null;
            }
            $errors[$schema->getStatePath().'.'.implode('.', $segments)] = $messages;
        }
        throw ValidationException::withMessages($errors);
    }
}
