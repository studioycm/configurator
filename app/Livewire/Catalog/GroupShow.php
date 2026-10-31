<?php

namespace App\Livewire\Catalog;

use App\DTO\CatalogSnapshot;
use App\Models\Group;
use App\Services\CatalogAvailability;
use App\Services\CatalogCards;
use App\Services\CatalogJourney;
use App\Services\CatalogNavigation;
use App\Services\CatalogPresentation;
use App\Services\CatalogProductComparison;
use App\Services\CatalogSnapshots;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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

    #[Locked]
    public ?string $journeyEnvelope = null;

    protected CatalogCards $cards;

    public function boot(CatalogCards $cards): void
    {
        $this->cards = $cards;
    }

    public function mount(Group $group): void
    {
        $this->groupId = (string) $group->id;
        if ($group->children()->exists()) {
            $query = Index::queryFromUrl(request()->query('g', []), (int) $group->id, (string) request()->server('QUERY_STRING', ''));
            $navigation = app(CatalogNavigation::class)->get(auth()->user(), $query);
            $this->redirectRoute('catalog.index', ['g' => Index::urlState($navigation['query'])]);

            return;
        }
        $raw = request()->query('r');
        $this->journeyEnvelope = $raw === null ? null : (is_string($raw) && strlen($raw) <= 8192 ? $raw : 'invalid');
    }

    /** @param array<string, mixed> $criteria @return array<string, mixed> */
    #[Json]
    public function loadCards(mixed $criteria, mixed $revision, mixed $requestId, mixed $displayVersion = null): array
    {
        abort_unless(auth()->check(), 401);
        try {
            Validator::make(compact('criteria', 'revision', 'requestId', 'displayVersion'), [
                'criteria' => ['required', 'array', 'max:4'],
                'revision' => ['required', 'string'],
                'requestId' => ['required', 'string'],
                'displayVersion' => ['nullable', 'string', 'regex:/^(?:defaults:v1|[0-9A-HJKMNP-TV-Z]{26})$/D'],
            ])->validate();

            return $this->cards->get((int) $this->groupId, $criteria, $revision, $requestId, $displayVersion);
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

    /** @return array<string, mixed> */
    #[Json]
    public function loadComparison(mixed $productIds, mixed $requestId): array
    {
        abort_unless(auth()->check(), 401);
        try {
            Validator::make(compact('productIds', 'requestId'), [
                'productIds' => ['required', 'array', 'list', 'size:2'],
                'requestId' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9:-]+\z/D'],
            ])->validate();
            $result = app(CatalogProductComparison::class)->get(auth()->user(), (int) $this->groupId, $productIds);

            return [...$result, 'requestId' => $requestId, 'componentId' => $this->getId()];
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
        $availability = app(CatalogAvailability::class)->visibleGroups();
        $availableGroup = $availability->firstWhere('id', $group->id);
        abort_if($availableGroup === null, 404);
        $isBranch = (bool) $availableGroup->children_exists;
        $journey = app(CatalogJourney::class);
        $resolvedJourney = $journey->resolve(auth()->user(), $this->journeyEnvelope, (int) $group->id);
        $journeyState = $resolvedJourney['state'];
        $journeyData = $journeyState === null ? null : $journey->forClient($journeyState);
        $journeyNotice = $resolvedJourney['notice'];
        $catalogReturnUrl = $journeyState === null ? route('catalog.index') : $journey->catalogUrl($journeyState);
        $children = $group->children()->whereKey($availability->modelKeys())->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'description']);
        $snapshot = null;
        if (! $isBranch) {
            try {
                $snapshots = app(CatalogSnapshots::class);
                for ($attempt = 0; $attempt < 2; $attempt++) {
                    $prepared = $snapshots->get((int) $this->groupId);
                    $snapshot = DB::transaction(function () use ($snapshots, $prepared): ?CatalogSnapshot {
                        if ($snapshots->revision((int) $this->groupId) !== $prepared->revision()) {
                            return null;
                        }
                        $record = Group::query()->findOrFail($this->groupId);
                        $presentation = app(CatalogPresentation::class);
                        $shared = $presentation->current();
                        $available = $presentation->resolveCategoryIds($shared['settings'], [$record]);

                        return new CatalogSnapshot([...$prepared->data, 'displayVersion' => $shared['version'], 'presentation' => $presentation->effective($record, $shared['settings'], $available)]);
                    }, attempts: 1);
                    if ($snapshot !== null) {
                        break;
                    }
                }
            } catch (Throwable $exception) {
                if (! $exception instanceof HttpExceptionInterface) {
                    report($exception);
                }
            }
        }

        return view('livewire.catalog.group-show', ['group' => $group, 'ancestors' => $group->ancestorTrail(), 'children' => $children, 'isBranch' => $isBranch, 'snapshot' => $snapshot,
            'journeyData' => $journeyData, 'journeyNotice' => $journeyNotice, 'catalogReturnUrl' => $catalogReturnUrl])
            ->title($group->name)->layoutData(['subtitle' => $group->description]);
    }
}
