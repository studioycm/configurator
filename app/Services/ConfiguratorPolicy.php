<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final readonly class ConfiguratorPolicy
{
    public const string UNRESTRICTED_CONTEXT = 'All';

    private function __construct(public string $codeSeparator = '-') {}

    /** @param array<string, mixed> $overrides */
    public static function resolve(array $overrides): self
    {
        if ($overrides !== []) {
            throw ValidationException::withMessages(['policy_overrides' => 'No policy overrides are currently supported.']);
        }

        return new self;
    }

    /** @param list<string> $legal */
    public function selection(array $legal, ?string $current, ?string $remembered, string $default): ?string
    {
        if (in_array($current, $legal, true)) {
            return $current;
        }
        if ($current === null && in_array($remembered, $legal, true)) {
            return $remembered;
        }

        return in_array($default, $legal, true) ? $default : ($legal[0] ?? null);
    }
}
