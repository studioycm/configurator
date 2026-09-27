<?php

namespace App\Livewire\Catalog;

use App\Models\Group;
use App\Services\CatalogCards;
use App\Services\CatalogSnapshots;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Json;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Layout('components.layouts.catalog')]
class GroupShow extends Component
{
    #[Locked]
    public string $groupId;

    protected CatalogCards $cards;

    public function boot(CatalogCards $cards): void
    {
        $this->cards = $cards;
    }

    public function mount(Group $group): void
    {
        $this->groupId = (string) $group->id;
    }

    /** @param array<string, mixed> $criteria @return array<string, mixed> */
    #[Json]
    public function loadCards(mixed $criteria, mixed $revision, mixed $requestId): array
    {
        abort_unless(auth()->check(), 401);
        try {
            Validator::make(compact('criteria', 'revision', 'requestId'), [
                'criteria' => ['required', 'array', 'max:4'],
                'revision' => ['required', 'string'],
                'requestId' => ['required', 'string'],
            ])->validate();

            return $this->cards->get((int) $this->groupId, $criteria, $revision, $requestId);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (AuthorizationException) {
            abort(403);
        } catch (Throwable $exception) {
            if (! $exception instanceof ValidationException && ! $exception instanceof HttpExceptionInterface) {
                report($exception);
            }
            throw $exception;
        }
    }

    public function render(): View
    {
        $group = Group::findOrFail($this->groupId);
        $children = $group->children()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'description']);
        $snapshot = null;
        if ($children->isEmpty()) {
            try {
                $snapshot = app(CatalogSnapshots::class)->get((int) $this->groupId);
            } catch (Throwable $exception) {
                if (! $exception instanceof HttpExceptionInterface) {
                    report($exception);
                }
            }
        }

        return view('livewire.catalog.group-show', ['group' => $group, 'ancestors' => $group->ancestorTrail(), 'children' => $children, 'snapshot' => $snapshot])
            ->title($group->name)->layoutData(['subtitle' => $group->description]);
    }
}
