<?php

namespace Database\Seeders;

use App\Models\ConfigAttribute;
use App\Models\ConfigOption;
use App\Models\ConfigProfile;
use App\Models\OptionRule;
use App\Models\ProductConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ConfiguratorRuntimeMetadataSeeder extends Seeder
{
    private const PROFILE_SLUG = 'd60s-p16-03-configurator';

    private const CONFIGURATION_CODE = 'D60S-P16-03-DEMO-A';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profile = ConfigProfile::query()
            ->where('slug', self::PROFILE_SLUG)
            ->first();

        if (! $profile instanceof ConfigProfile) {
            $this->command?->warn('Skipping ConfiguratorRuntimeMetadataSeeder: mounted demo configurator was not found.');

            return;
        }

        $attributes = ConfigAttribute::query()
            ->whereBelongsTo($profile, 'configProfile')
            ->with('options')
            ->get()
            ->keyBy('slug');

        $options = $attributes
            ->flatMap(fn (ConfigAttribute $attribute): array => $attribute->options
                ->mapWithKeys(fn (ConfigOption $option): array => [
                    $this->optionKey($attribute->slug, $option->code) => $option,
                ])
                ->all());

        $rules = OptionRule::query()
            ->whereBelongsTo($profile, 'configProfile')
            ->with(['option.attribute', 'targetAttribute'])
            ->get()
            ->keyBy(fn (OptionRule $rule): string => $this->ruleKey(
                $rule->option?->attribute?->slug ?? '',
                $rule->option?->code ?? '',
                $rule->targetAttribute?->slug ?? '',
            ));

        $profile->forceFill([
            'runtime_context_schema' => $this->runtimeContextSchema(),
        ])->save();

        foreach ($this->attributeSchemas() as $attributeSlug => $schema) {
            $attribute = $attributes->get($attributeSlug);

            if (! $attribute instanceof ConfigAttribute) {
                $this->command?->warn("Skipping missing attribute [{$attributeSlug}] in metadata seeder.");

                continue;
            }

            $attribute->forceFill([
                'ui_schema' => $schema,
            ])->save();
        }

        foreach ($this->optionMetadata() as $optionKey => $metadata) {
            $option = $options->get($optionKey);

            if (! $option instanceof ConfigOption) {
                $this->command?->warn("Skipping missing option [{$optionKey}] in metadata seeder.");

                continue;
            }

            $option->forceFill([
                'ui_meta' => $metadata,
            ])->save();
        }

        foreach ($this->ruleMetadata($options) as $ruleKey => $metadata) {
            $rule = $rules->get($ruleKey);

            if (! $rule instanceof OptionRule) {
                $this->command?->warn("Skipping missing rule [{$ruleKey}] in metadata seeder.");

                continue;
            }

            $rule->forceFill($metadata)->save();
        }

        $configuration = ProductConfiguration::query()
            ->where('configuration_code', self::CONFIGURATION_CODE)
            ->first();

        if ($configuration instanceof ProductConfiguration) {
            $configuration->forceFill([
                'resolved_state' => $this->resolvedState($attributes, $options),
            ])->save();
        }

        $this->command?->info('Configurator runtime metadata seeded for d60s-p16-03-configurator.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runtimeContextSchema(): array
    {
        return [
            [
                'key' => 'territory',
                'label' => 'Territory',
                'type' => 'select',
                'required' => true,
                'default' => 'Global',
                'options' => [
                    'Global',
                    'Europe',
                    'Germany',
                    'USA',
                    'Australia',
                    'Russia',
                ],
            ],
            [
                'key' => 'application',
                'label' => 'Application',
                'type' => 'select',
                'required' => true,
                'default' => 'Show All',
                'options' => [
                    'Show All',
                    'Industry',
                    'Water Supply',
                    'Agriculture',
                    'Wastewater',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function attributeSchemas(): array
    {
        return [
            'flange-standard' => $this->attributeSchema('general', 'Select the flange drilling standard.'),
            'kinetic-valve-body-material' => $this->attributeSchema('kinetic-valve', 'Body material drives downstream compatibility rules.'),
            'kinetic-valve-seal-material' => $this->attributeSchema('kinetic-valve', 'Seal material may be restricted by body material.'),
            'kinetic-valve-seat-material' => $this->attributeSchema('kinetic-valve', 'Seat material affects the O-ring compatibility set.'),
            'kinetic-valve-bolt-set-material' => $this->attributeSchema('kinetic-valve', 'Select the bolt set material.'),
            'kinetic-valve-float-material' => $this->attributeSchema('kinetic-valve', 'Choose the kinetic valve float material.'),
            'automatic-valve-body-material' => $this->attributeSchema('automatic-valve', 'Select the automatic valve body material.'),
            'automatic-valve-seal-material' => $this->attributeSchema('automatic-valve', 'Seal material may be narrowed by upstream choices.'),
            'automatic-valve-float-material' => $this->attributeSchema('automatic-valve', 'Choose the automatic valve float material.'),
            'o-ring-material' => $this->attributeSchema('sealing', 'O-ring options may change based on seat material.'),
            'air-release-outlet' => $this->attributeSchema('connections', 'Select the air release outlet standard.'),
            'pressure-release-outlet' => $this->attributeSchema('connections', 'Pressure release outlet has the richest rule set in the demo.'),
            'screen-cover-material' => $this->attributeSchema('connections', 'Screen cover availability is controlled by outlet selection.'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function optionMetadata(): array
    {
        return [
            $this->optionKey('flange-standard', 'D6') => [
                'hint' => 'DIN 16 restricts body materials to stainless / duplex options.',
            ],
            $this->optionKey('kinetic-valve-body-material', 'CS') => [
                'label_short' => 'Cast Steel',
                'hint' => 'Default steel-bodied configuration.',
            ],
            $this->optionKey('kinetic-valve-body-material', 'S6') => [
                'label_short' => 'SS316',
                'hint' => 'Stainless option compatible with DIN 16.',
            ],
            $this->optionKey('kinetic-valve-body-material', 'DX') => [
                'label_short' => 'Duplex',
                'hint' => 'Triggers the strongest downstream restrictions in the demo.',
            ],
            $this->optionKey('kinetic-valve-seat-material', 'S3') => [
                'label_short' => 'SS316 Seat',
            ],
            $this->optionKey('automatic-valve-body-material', 'S7') => [
                'label_short' => 'SS316',
            ],
            $this->optionKey('air-release-outlet', 'PB') => [
                'label_short' => 'PP BSPT',
            ],
            $this->optionKey('air-release-outlet', 'PN') => [
                'label_short' => 'PP NPT',
            ],
            $this->optionKey('pressure-release-outlet', 'SP') => [
                'label_short' => 'SS Plug',
                'disabled_by_default' => true,
            ],
            $this->optionKey('pressure-release-outlet', 'BP') => [
                'label_short' => 'Brass Plug',
                'hidden_by_default' => true,
            ],
            $this->optionKey('pressure-release-outlet', 'SV') => [
                'label_short' => 'SS Ball Valve',
            ],
            $this->optionKey('pressure-release-outlet', 'BV') => [
                'label_short' => 'Brass Ball Valve',
                'hint' => 'Default outlet selection on the demo profile.',
            ],
            $this->optionKey('screen-cover-material', 'P2') => [
                'label_short' => 'PP',
            ],
            $this->optionKey('screen-cover-material', 'D2') => [
                'label_short' => 'DI',
            ],
        ];
    }

    /**
     * @param  Collection<string, ConfigOption>  $options
     * @return array<string, array<string, mixed>>
     */
    private function ruleMetadata(Collection $options): array
    {
        return [
            $this->ruleKey('pressure-release-outlet', 'W0', 'screen-cover-material') => [
                'dependency_type' => 'hidden',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'screen-cover-material', 'P2'),
                            'hint' => 'Only polypropylene cover is available when no pressure release outlet is used.',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
            $this->ruleKey('pressure-release-outlet', 'BV', 'screen-cover-material') => [
                'dependency_type' => 'disabled',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'label_overrides' => [
                        [
                            'option_id' => $this->optionId($options, 'screen-cover-material', 'D2'),
                            'label' => 'Ductile Iron (Europe)',
                        ],
                    ],
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'screen-cover-material', 'D2'),
                            'hint' => 'European context rule is active for the Brass Ball Valve setup.',
                        ],
                    ],
                    'activate_if' => [
                        [
                            'source' => 'context.territory',
                            'operator' => '=',
                            'value' => 'Europe',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 10,
            ],
            $this->ruleKey('flange-standard', 'D6', 'kinetic-valve-body-material') => [
                'dependency_type' => 'hidden',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'kinetic-valve-body-material', 'S6'),
                            'hint' => 'DIN 16 compatible stainless option.',
                        ],
                        [
                            'option_id' => $this->optionId($options, 'kinetic-valve-body-material', 'DX'),
                            'hint' => 'DIN 16 compatible duplex option.',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
            $this->ruleKey('kinetic-valve-body-material', 'DX', 'kinetic-valve-seal-material') => [
                'dependency_type' => 'disabled',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'kinetic-valve-seal-material', 'VT'),
                            'hint' => 'Viton is required for the Duplex 5A body material.',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
            $this->ruleKey('kinetic-valve-body-material', 'DX', 'automatic-valve-seal-material') => [
                'dependency_type' => 'hidden',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'automatic-valve-seal-material', 'V2'),
                            'hint' => 'Only Viton is permitted for the automatic valve seal with Duplex 5A.',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
            $this->ruleKey('kinetic-valve-seat-material', 'S3', 'o-ring-material') => [
                'dependency_type' => 'disabled',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'label_overrides' => [
                        [
                            'option_id' => $this->optionId($options, 'o-ring-material', 'V3'),
                            'label' => 'Viton (Recommended)',
                        ],
                    ],
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'o-ring-material', 'V3'),
                            'hint' => 'Recommended with the SS316 seat material.',
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
            $this->ruleKey('kinetic-valve-body-material', 'DX', 'pressure-release-outlet') => [
                'dependency_type' => 'hidden',
                'rule_payload' => [
                    'effect' => 'restrict_allowed_options',
                    'hints' => [
                        [
                            'option_id' => $this->optionId($options, 'pressure-release-outlet', 'BV'),
                            'hint' => 'Brass Ball Valve remains available for the Duplex 5A combination.',
                        ],
                    ],
                    'activate_if' => [
                        [
                            'source' => 'context.application',
                            'operator' => 'in',
                            'value' => ['Show All', 'Wastewater'],
                        ],
                    ],
                ],
                'is_active' => true,
                'priority' => 0,
            ],
        ];
    }

    /**
     * @param  Collection<string, ConfigAttribute>  $attributes
     * @param  Collection<string, ConfigOption>  $options
     * @return array<string, mixed>
     */
    private function resolvedState(Collection $attributes, Collection $options): array
    {
        return [
            'profile_slug' => self::PROFILE_SLUG,
            'configuration_code' => self::CONFIGURATION_CODE,
            'selection' => [
                (string) $this->attributeId($attributes, 'flange-standard') => $this->optionId($options, 'flange-standard', 'D6'),
                (string) $this->attributeId($attributes, 'kinetic-valve-body-material') => $this->optionId($options, 'kinetic-valve-body-material', 'S6'),
                (string) $this->attributeId($attributes, 'kinetic-valve-seal-material') => $this->optionId($options, 'kinetic-valve-seal-material', 'EP'),
                (string) $this->attributeId($attributes, 'kinetic-valve-seat-material') => $this->optionId($options, 'kinetic-valve-seat-material', 'BR'),
                (string) $this->attributeId($attributes, 'kinetic-valve-float-material') => $this->optionId($options, 'kinetic-valve-float-material', 'PC'),
                (string) $this->attributeId($attributes, 'pressure-release-outlet') => $this->optionId($options, 'pressure-release-outlet', 'BV'),
                (string) $this->attributeId($attributes, 'screen-cover-material') => $this->optionId($options, 'screen-cover-material', 'P2'),
            ],
            'context' => [
                'territory' => 'Europe',
                'application' => 'Wastewater',
            ],
            'notes' => 'Legacy demo snapshot preserved as a partial known configuration state for admin/resource review.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeSchema(string $group, string $helpText): array
    {
        return [
            'group' => $group,
            'help_text' => $helpText,
            'auto_select_first_allowed' => true,
        ];
    }

    private function optionKey(string $attributeSlug, string $optionCode): string
    {
        return "{$attributeSlug}::{$optionCode}";
    }

    private function ruleKey(string $sourceAttributeSlug, string $sourceOptionCode, string $targetAttributeSlug): string
    {
        return "{$sourceAttributeSlug}::{$sourceOptionCode}::{$targetAttributeSlug}";
    }

    /**
     * @param  Collection<string, ConfigAttribute>  $attributes
     */
    private function attributeId(Collection $attributes, string $attributeSlug): int
    {
        /** @var ConfigAttribute|null $attribute */
        $attribute = $attributes->get($attributeSlug);

        return (int) $attribute?->getKey();
    }

    /**
     * @param  Collection<string, ConfigOption>  $options
     */
    private function optionId(Collection $options, string $attributeSlug, string $optionCode): int
    {
        /** @var ConfigOption|null $option */
        $option = $options->get($this->optionKey($attributeSlug, $optionCode));

        return (int) $option?->getKey();
    }
}
