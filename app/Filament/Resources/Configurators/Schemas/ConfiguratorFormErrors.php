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
    public static function run(Closure $save, ?Schema $schema = null, ?string $removePrefix = null): mixed
    {
        try {
            return $save();
        } catch (Throwable $exception) {
            self::rethrow($exception, $schema, $removePrefix);
        }
    }

    public static function rethrow(Throwable $exception, ?Schema $schema, ?string $removePrefix = null): never
    {
        if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException || $exception instanceof HttpExceptionInterface) {
            throw $exception;
        }
        if (! $exception instanceof ValidationException) {
            report($exception);
            $exception = ValidationException::withMessages(['save' => __('configurator.validation.changes_could_not_be_saved_your_draft')]);
        }
        if ($schema === null) {
            throw $exception;
        }
        $state = $schema->getRawState();
        $state = $state instanceof Arrayable ? $state->toArray() : $state;
        $errors = [];
        foreach ($exception->errors() as $path => $messages) {
            $path = $removePrefix === null ? $path : preg_replace($removePrefix, '', $path);
            if (array_key_exists('condition_blocks', $state)) {
                $path = self::ruleConditionPath($path, $state);
            }
            $cursor = $state;
            $segments = [];
            foreach (explode('.', $path) as $segment) {
                if (is_array($cursor) && ctype_digit($segment) && ! array_key_exists($segment, $cursor) && ! array_is_list($cursor)) {
                    $segment = (string) (array_keys($cursor)[(int) $segment] ?? $segment);
                }
                $segments[] = $segment;
                $cursor = is_array($cursor) ? ($cursor[$segment] ?? null) : null;
            }
            $key = $schema->getStatePath().'.'.implode('.', $segments);
            $errors[$key] = array_key_exists('condition_blocks', $state) ? [...($errors[$key] ?? []), ...$messages] : $messages;
        }
        throw ValidationException::withMessages($errors);
    }

    /** @param array<string, mixed> $state */
    private static function ruleConditionPath(string $path, array $state): string
    {
        if ($path === 'conditions') {
            return 'condition_blocks';
        }
        if (! preg_match('/^conditions\.(\d+)(?:\.(.*))?$/', $path, $match)) {
            return $path;
        }

        $block = array_values($state['condition_blocks'])[(int) $match[1]] ?? [];
        $prefix = 'condition_blocks.'.$match[1].'.data';
        $suffix = $match[2] ?? '';
        $predicate = $block['data'] ?? [];
        if (preg_match('/^conditions\.(\d+)(?:\.(.*))?$/', $suffix, $nested)) {
            $prefix .= '.conditions.'.$nested[1];
            $predicate = array_values($predicate['conditions'] ?? [])[(int) $nested[1]] ?? [];
            $suffix = $nested[2] ?? '';
        }
        if ($suffix === 'operand') {
            $suffix = in_array($predicate['operator'] ?? null, ['In', 'NotIn'], true) ? 'operand_list' : 'operand_text';
        }

        return $prefix.($suffix === '' ? '' : '.'.$suffix);
    }
}
