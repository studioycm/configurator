<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class MappingSetsField extends Field
{
    protected string $view = 'filament.forms.components.mapping-sets-field';

    protected array|Closure $sourceChoices = [];

    protected array|Closure $targetChoices = [];

    public function sourceChoices(array|Closure $choices): static
    {
        $this->sourceChoices = $choices;

        return $this;
    }

    public function targetChoices(array|Closure $choices): static
    {
        $this->targetChoices = $choices;

        return $this;
    }

    /** @return array<int|string, string> */
    public function getSourceChoices(): array
    {
        return $this->evaluate($this->sourceChoices);
    }

    /** @return array<int|string, string> */
    public function getTargetChoices(): array
    {
        return $this->evaluate($this->targetChoices);
    }
}
