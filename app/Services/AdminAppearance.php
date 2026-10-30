<?php

namespace App\Services;

use App\Models\AdminAppearanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAppearance
{
    /** @var array<string, bool|int|string>|null */
    private ?array $resolved = null;

    /** @var array{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}|null */
    private ?array $recoveryReport = null;

    private bool $recoveryLogged = false;

    public const string CACHE_KEY = 'admin-appearance:singleton:v1';

    /** @return array<string, bool|int|string> */
    public function defaults(): array
    {
        return ['cell_padding_block' => 6, 'cell_padding_inline' => 10, 'workspace_gap' => 8, 'header_height' => 48, 'logo_height' => 28, 'sidebar_width' => 216, 'collapsed_sidebar_width' => 58, 'navigation_padding_block' => 6, 'control_height' => 32, 'dialog_control_style' => 'icons', 'dialog_control_radius' => 7, 'modal_width' => 'medium', 'slide_over_width' => 'medium', 'show_configurator_loading_indicator' => false, 'page_title_size' => 20];
    }

    /** @return array<string, array{0:int, 1:int}> */
    public function ranges(): array
    {
        return ['dialog_control_radius' => [4, 10], 'cell_padding_block' => [4, 8], 'cell_padding_inline' => [8, 12], 'workspace_gap' => [6, 12], 'header_height' => [40, 64], 'logo_height' => [20, 32], 'page_title_size' => [14, 32], 'sidebar_width' => [192, 320], 'collapsed_sidebar_width' => [58, 80], 'navigation_padding_block' => [4, 8], 'control_height' => [30, 36]];
    }

    /** @param array<string, mixed> $settings @return array<string, bool|int|string> */
    public function validate(array $settings): array
    {
        $rules = ['settings' => ['required', 'array:'.implode(',', array_keys($this->defaults()))], ...$this->fieldRules()];
        $validated = Validator::make(['settings' => $settings], $rules)->validate()['settings'];
        foreach ($this->ranges() as $key => $range) {
            $validated[$key] = (int) $validated[$key];
        }
        $validated['show_configurator_loading_indicator'] = (bool) $validated['show_configurator_loading_indicator'];
        if ($validated['logo_height'] > $validated['header_height'] - 12) {
            throw ValidationException::withMessages(['settings.logo_height' => __('admin.appearance.validation.the_logo_must_fit_within_the_header')]);
        }
        if ((int) ceil($validated['page_title_size'] * 1.2) > $validated['header_height'] - 12) {
            throw ValidationException::withMessages(['settings.page_title_size' => __('admin.appearance.validation.the_title_must_fit_within_the_header')]);
        }

        return $validated;
    }

    /** @return array<string, array<int, mixed>> */
    private function fieldRules(): array
    {
        $rules = [];
        foreach ($this->ranges() as $key => [$min, $max]) {
            $rules['settings.'.$key] = ['required', 'integer', 'between:'.$min.','.$max];
        }
        foreach (['modal_width', 'slide_over_width'] as $key) {
            $rules['settings.'.$key] = ['required', Rule::in(['small', 'medium', 'large'])];
        }
        $rules['settings.dialog_control_style'] = ['required', Rule::in(['icons', 'icons_text', 'text'])];
        $rules['settings.show_configurator_loading_indicator'] = ['required', 'boolean'];

        return $rules;
    }

    /** @return array<string, bool|int|string> */
    private function recoverStored(mixed $stored): array
    {
        $defaults = $this->defaults();
        $report = ['defaulted_fields' => [], 'ignored_unknown_count' => 0, 'invalid_payload' => ! is_array($stored)];
        $candidate = $defaults;
        if (is_array($stored)) {
            $report['ignored_unknown_count'] = count(array_diff_key($stored, $defaults));
            $candidate = array_replace($defaults, array_intersect_key($stored, $defaults));
            $validator = Validator::make(['settings' => $candidate], $this->fieldRules());
            if ($validator->fails()) {
                foreach (array_keys($validator->failed()) as $path) {
                    $field = substr($path, strlen('settings.'));
                    $candidate[$field] = $defaults[$field];
                    $report['defaulted_fields'][] = $field;
                }
            }
            foreach (['logo_height' => 1, 'page_title_size' => 1.2] as $field => $scale) {
                if ((int) ceil((int) $candidate[$field] * $scale) > (int) $candidate['header_height'] - 12) {
                    $candidate[$field] = $defaults[$field];
                    $report['defaulted_fields'][] = $field;
                }
            }
        }
        $validated = $this->validate($candidate);
        $this->recoveryReport = $report['defaulted_fields'] !== [] || $report['ignored_unknown_count'] > 0 || $report['invalid_payload'] ? $report : null;
        if ($this->recoveryReport !== null && ! $this->recoveryLogged) {
            Log::warning('Stored admin appearance settings required recovery.', $this->recoveryReport);
            $this->recoveryLogged = true;
        }

        return $validated;
    }

    /** @return array{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}|null */
    public function recovery(): ?array
    {
        return $this->recoveryReport;
    }

    /** @return array<string, bool|int|string> */
    public function current(): array
    {
        $transactional = DB::transactionLevel() > 0;
        if ($transactional) {
            $this->resolved = null;
        } elseif ($this->resolved !== null) {
            return $this->resolved;
        }
        if (! Schema::hasTable('admin_appearance_settings')) {
            $this->recoveryReport = null;

            return $transactional ? $this->defaults() : ($this->resolved = $this->defaults());
        }
        $read = fn (): mixed => AdminAppearanceSettings::findOrFail(1)->settings;
        $settings = $transactional ? $read() : Cache::remember(self::CACHE_KEY, 3600, $read);
        $settings = $this->recoverStored($settings);

        return $transactional ? $settings : ($this->resolved = $settings);
    }

    /** @param array<string, bool|int|string> $settings */
    public function rememberSaved(array $settings): void
    {
        $this->resolved = DB::transactionLevel() > 0 ? null : $settings;
        $this->recoveryReport = null;
    }

    /** @param array<string, mixed>|null $draft @return array<string, string> */
    public function variables(?array $draft = null): array
    {
        $settings = $draft === null ? $this->current() : array_replace($this->defaults(), array_intersect_key($draft, $this->defaults()));
        $variables = [];
        $mapping = ['dialog_control_radius' => 'dialog-control-radius', 'cell_padding_block' => 'cell-padding-block', 'cell_padding_inline' => 'cell-padding-inline', 'workspace_gap' => 'shell-gap', 'header_height' => 'shell-header-min-height', 'logo_height' => 'shell-logo-height', 'page_title_size' => 'shell-heading-size', 'sidebar_width' => 'shell-sidebar-width', 'collapsed_sidebar_width' => 'shell-rail-width', 'navigation_padding_block' => 'navigation-padding-block', 'control_height' => 'shell-desktop-target'];
        foreach ($mapping as $key => $token) {
            [$min, $max] = $this->ranges()[$key];
            $variables['--aquestia-'.$token] = max($min, min($max, (int) $settings[$key])).'px';
        }
        $variables['--aquestia-shell-brand-height'] = $variables['--aquestia-shell-header-min-height'];
        $variables['--aquestia-shell-heading-line-height'] = (int) ceil((int) $variables['--aquestia-shell-heading-size'] * 1.2).'px';
        foreach (['modal_width', 'slide_over_width'] as $key) {
            $variables['--aquestia-'.str_replace('_', '-', $key)] = (['small' => 640, 'medium' => 960, 'large' => 1280][$settings[$key]] ?? 960).'px';
        }

        $variables['--aquestia-dialog-label-display'] = $settings['dialog_control_style'] === 'icons' ? 'none' : 'inline';
        $variables['--aquestia-dialog-icon-display'] = $settings['dialog_control_style'] === 'text' ? 'none' : 'inline-flex';

        return $variables;
    }

    /** @param array<string, mixed>|null $draft */
    public function style(?array $draft = null): string
    {
        return collect($this->variables($draft))->map(fn (string $value, string $key): string => $key.':'.$value)->implode(';');
    }
}
