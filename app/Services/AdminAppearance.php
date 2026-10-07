<?php

namespace App\Services;

use App\Models\AdminAppearanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAppearance
{
    /** @var array<string, int|string>|null */
    private ?array $resolved = null;

    public const string CACHE_KEY = 'admin-appearance:singleton:v1';

    /** @return array<string, int|string> */
    public function defaults(): array
    {
        return ['cell_padding_block' => 6, 'cell_padding_inline' => 10, 'workspace_gap' => 8, 'header_height' => 48, 'logo_height' => 28, 'sidebar_width' => 216, 'collapsed_sidebar_width' => 58, 'navigation_padding_block' => 6, 'control_height' => 32, 'modal_width' => 'medium', 'slide_over_width' => 'medium'];
    }

    /** @return array<string, array{0:int, 1:int}> */
    public function ranges(): array
    {
        return ['cell_padding_block' => [4, 8], 'cell_padding_inline' => [8, 12], 'workspace_gap' => [6, 12], 'header_height' => [40, 64], 'logo_height' => [20, 32], 'sidebar_width' => [192, 320], 'collapsed_sidebar_width' => [58, 80], 'navigation_padding_block' => [4, 8], 'control_height' => [30, 36]];
    }

    /** @param array<string, mixed> $settings @return array<string, int|string> */
    public function validate(array $settings): array
    {
        $rules = ['settings' => ['required', 'array:'.implode(',', array_keys($this->defaults()))]];
        foreach ($this->ranges() as $key => [$min, $max]) {
            $rules['settings.'.$key] = ['required', 'integer', 'between:'.$min.','.$max];
        }
        foreach (['modal_width', 'slide_over_width'] as $key) {
            $rules['settings.'.$key] = ['required', Rule::in(['small', 'medium', 'large'])];
        }
        $validated = Validator::make(['settings' => $settings], $rules)->validate()['settings'];
        foreach ($this->ranges() as $key => $range) {
            $validated[$key] = (int) $validated[$key];
        }
        if ($validated['logo_height'] > $validated['header_height'] - 12) {
            throw ValidationException::withMessages(['settings.logo_height' => 'The logo must fit within the header with 6 px padding above and below.']);
        }

        return $validated;
    }

    /** @return array<string, int|string> */
    public function current(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        if (! Schema::hasTable('admin_appearance_settings')) {
            return $this->resolved = $this->defaults();
        }
        $read = fn (): array => $this->validate(AdminAppearanceSettings::findOrFail(1)->settings);

        return $this->resolved = DB::transactionLevel() > 0 ? $read() : Cache::remember(self::CACHE_KEY, 3600, $read);
    }

    /** @param array<string, int|string> $settings */
    public function rememberSaved(array $settings): void
    {
        $this->resolved = $settings;
    }

    /** @param array<string, mixed>|null $draft @return array<string, string> */
    public function variables(?array $draft = null): array
    {
        $settings = $draft === null ? $this->current() : array_replace($this->defaults(), array_intersect_key($draft, $this->defaults()));
        $variables = [];
        $mapping = ['cell_padding_block' => 'cell-padding-block', 'cell_padding_inline' => 'cell-padding-inline', 'workspace_gap' => 'shell-gap', 'header_height' => 'shell-header-min-height', 'logo_height' => 'shell-logo-height', 'sidebar_width' => 'shell-sidebar-width', 'collapsed_sidebar_width' => 'shell-rail-width', 'navigation_padding_block' => 'navigation-padding-block', 'control_height' => 'shell-desktop-target'];
        foreach ($mapping as $key => $token) {
            [$min, $max] = $this->ranges()[$key];
            $variables['--aquestia-'.$token] = max($min, min($max, (int) $settings[$key])).'px';
        }
        $variables['--aquestia-shell-brand-height'] = $variables['--aquestia-shell-header-min-height'];
        foreach (['modal_width', 'slide_over_width'] as $key) {
            $variables['--aquestia-'.str_replace('_', '-', $key)] = (['small' => 640, 'medium' => 960, 'large' => 1280][$settings[$key]] ?? 960).'px';
        }

        return $variables;
    }

    /** @param array<string, mixed>|null $draft */
    public function style(?array $draft = null): string
    {
        return collect($this->variables($draft))->map(fn (string $value, string $key): string => $key.':'.$value)->implode(';');
    }
}
