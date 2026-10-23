<?php

namespace App\Filament\Resources\Configurators\Schemas;

use App\ConfiguratorSubtitleMode;
use App\Filament\Resources\FormHints;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConfiguratorValueTextFields
{
    /** @param Closure(Get): string $masterName
     * @return list<Component>
     */
    public static function fields(Closure $masterName): array
    {
        return [
            Hidden::make('subtitle_mode')->default(fn (Get $get): string => self::blank($get('display_value_override')) ? 'none' : 'custom')->rules(['required', Rule::enum(ConfiguratorSubtitleMode::class)]),
            Hidden::make('display_value_override')->rules(fn (Get $get): array => [$get('subtitle_mode') === 'custom' ? 'required' : 'nullable', 'string', 'max:255']),
            Hidden::make('_subtitle_custom_draft')->dehydrated(false),
            TextInput::make('label_override')->label(__('configurator.inclusions.values.fields.local_ui_name'))->maxLength(255)->trim(false)->live(onBlur: true)
                ->placeholder($masterName)->hintAction(FormHints::make(__('configurator.inclusions.values.hints.local_ui_name')))->columnSpanFull(),
            TextInput::make('_subtitle_text')->label(__('configurator.subtitles.local'))->maxLength(255)->trim(false)->dehydrated(false)->live(onBlur: true)
                ->hintAction(FormHints::make(__('configurator.inclusions.values.hints.local_display_value')))
                ->readOnly(fn (Get $get): bool => $get('subtitle_mode') === 'master')
                ->suffix(fn (Get $get): ?string => $get('subtitle_mode') === 'master' ? __('configurator.subtitles.linked') : null)
                ->afterStateHydrated(function (TextInput $component, Get $get) use ($masterName): void {
                    $component->state($get('subtitle_mode') === 'master' ? $masterName($get) : ($get('display_value_override') ?? ''));
                })
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $blank = self::blank($state);
                    $set('subtitle_mode', $blank ? 'none' : 'custom');
                    $set('display_value_override', $blank ? null : $state);
                    $set('_subtitle_custom_draft', $blank ? null : $state);
                })
                ->suffixActions([
                    Action::make('linkSubtitle')->label(__('configurator.subtitles.link'))->icon(Heroicon::Link)->authorize('manage-catalog')
                        ->visible(fn (Get $get): bool => $get('subtitle_mode') !== 'master')
                        ->action(function (Get $get, Set $set) use ($masterName): void {
                            Gate::authorize('manage-catalog');
                            if ($get('subtitle_mode') === 'custom') {
                                $set('_subtitle_custom_draft', $get('display_value_override'));
                            }
                            $set('subtitle_mode', 'master');
                            $set('display_value_override', null);
                            $set('_subtitle_text', $masterName($get));
                        }),
                    Action::make('unlinkSubtitle')->label(__('configurator.subtitles.unlink'))->icon(Heroicon::LinkSlash)->authorize('manage-catalog')
                        ->visible(fn (Get $get): bool => $get('subtitle_mode') === 'master')
                        ->action(function (Get $get, Set $set): void {
                            Gate::authorize('manage-catalog');
                            $custom = $get('_subtitle_custom_draft');
                            $blank = self::blank($custom);
                            $set('subtitle_mode', $blank ? 'none' : 'custom');
                            $set('display_value_override', $blank ? null : $custom);
                            $set('_subtitle_text', $blank ? '' : $custom);
                        }),
                ])->columnSpanFull(),
            View::make('filament.resources.configurator-value-preview')->viewData(function (Get $get) use ($masterName): array {
                $master = $masterName($get);
                $name = $get('label_override');
                $mode = ConfiguratorSubtitleMode::tryFrom((string) $get('subtitle_mode')) ?? ConfiguratorSubtitleMode::None;

                return ['name' => self::blank($name) ? $master : $name, 'subtitle' => $mode->resolve($get('display_value_override'), $master)];
            })->columnSpanFull(),
        ];
    }

    private static function blank(mixed $text): bool
    {
        return $text === null || (is_string($text) && trim($text) === '');
    }
}
