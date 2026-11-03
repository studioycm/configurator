<?php

namespace App\Http\Controllers;

use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\CatalogWriteFailure;
use App\CatalogWriteReplan;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\Services\ConfiguratorRuleWritingDrafts;
use App\Services\ConfiguratorRuleWritingInspection;
use App\Services\ConfiguratorRuleWritingInspectionPresenter;
use App\Services\ConfiguratorRuleWritingReconciliation;
use App\Services\ConfiguratorRuleWritingReferences;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ConfiguratorRuleWritingController extends Controller
{
    public const BODY_LIMIT = 20971520;

    public const OPERATIONS = ['references', 'draft-summaries', 'saved-rule-summaries', 'open-draft', 'create-draft', 'open-rule', 'update-draft', 'save-selected', 'inspect-draft', 'capture-latest', 'refresh-sections'];

    public function __invoke(Request $request, string $configurator, string $operation): JsonResponse
    {
        Gate::authorize('manage-catalog');
        abort_unless(config('configurator-writing.enabled') === true, 404);
        abort_unless($request->isJson(), 415);
        $raw = $request->getContent();
        abort_if(strlen($raw) > self::BODY_LIMIT, 413);
        try {
            $input = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(400, 'Use a valid JSON object.');
        }
        abort_unless(is_array($input) && str_starts_with(ltrim($raw), '{'), 400);
        $owner = Configurator::findOrFail($this->decimalId($configurator));
        /** @var User $actor */
        $actor = $request->user('web');
        try {
            $this->validateInput($operation, $input);
            $drafts = app(ConfiguratorRuleWritingDrafts::class);
            $data = match ($operation) {
                'references' => app(ConfiguratorRuleWritingReferences::class)->get($actor, $owner),
                'draft-summaries', 'saved-rule-summaries' => $this->summaries($owner, $operation, $input),
                'open-draft' => $this->editorResult($drafts->get($actor, $owner, $input['id'])),
                'create-draft' => $this->editorResult($drafts->create($actor, $owner, $input['id'], $input['authoring'], $input['sequence'])),
                'open-rule' => $this->openRule($actor, $owner, $input),
                'update-draft' => $this->editorResult($drafts->update($actor, $owner, $input['id'], $input['expected_revision'], $input['authoring'], $input['sequence'])),
                'save-selected' => $this->save($actor, $owner, $input['selection']),
                'inspect-draft' => app(ConfiguratorRuleWritingInspectionPresenter::class)->present(app(ConfiguratorRuleWritingInspection::class)->inspect($actor, $owner, $input['id'])),
                'capture-latest' => $this->capture($actor, $owner, $input),
                'refresh-sections' => $this->refreshSections($actor, $owner, $input),
            };

            return $this->response(['ok' => true, 'data' => $data]);
        } catch (CatalogWriteReplan $exception) {
            return $this->response(['ok' => false, 'type' => 'replan', 'errors' => $exception->errors()], 422);
        } catch (ValidationException $exception) {
            return $this->response(['ok' => false, 'type' => 'validation', 'errors' => $exception->errors()], 422);
        } catch (AuthorizationException|ModelNotFoundException|HttpException $exception) {
            throw $exception;
        } catch (CatalogWriteFailure $exception) {
            report($exception);

            return $this->response(['ok' => false, 'type' => 'failure', 'rolledBack' => $exception->rolledBack, 'message' => __('configurator.writing.write_failed')], 500);
        } catch (Throwable $exception) {
            report($exception);

            return $this->response(['ok' => false, 'type' => 'unknown', 'message' => __('configurator.writing.outcome_unknown')], 500);
        }
    }

    /** @param array<string,mixed> $input */
    private function validateInput(string $operation, array $input): void
    {
        $keys = match ($operation) {
            'references' => [],
            'draft-summaries', 'saved-rule-summaries' => ['page', 'search'],
            'open-draft', 'inspect-draft' => ['id'],
            'create-draft' => ['id', 'authoring', 'sequence'],
            'open-rule' => ['rule_id', 'new_draft_id'],
            'update-draft' => ['id', 'expected_revision', 'authoring', 'sequence'],
            'save-selected' => ['selection'],
            'capture-latest' => ['id', 'expected_revision'],
            'refresh-sections' => ['id', 'expected_revision', 'snapshot_json', 'sections', 'sequence'],
        };
        if (array_diff(array_keys($input), $keys) !== []) {
            throw ValidationException::withMessages(['request' => 'Only the operation fields may be submitted.']);
        }
        if (! in_array($operation, ['draft-summaries', 'saved-rule-summaries'], true)) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $input)) {
                    throw ValidationException::withMessages([$key => 'This field is required.']);
                }
            }
        }
        foreach (['page', 'expected_revision', 'sequence'] as $key) {
            if (array_key_exists($key, $input)) {
                $this->counter($input[$key], $key, $key === 'sequence' ? 0 : 1);
            }
        }
        foreach (['id', 'new_draft_id', 'rule_id', 'snapshot_json', 'search'] as $key) {
            if (array_key_exists($key, $input) && ! is_string($input[$key])) {
                throw ValidationException::withMessages([$key => 'Use text for this field.']);
            }
        }
        foreach (['authoring', 'sections', 'selection'] as $key) {
            if (array_key_exists($key, $input) && ! is_array($input[$key])) {
                throw ValidationException::withMessages([$key => 'Use an array for this field.']);
            }
        }
        if (isset($input['selection'])) {
            foreach ($input['selection'] as $index => $item) {
                if (! is_array($item)) {
                    throw ValidationException::withMessages(['selection' => 'Choose explicit draft versions.']);
                }
                $this->counter($item['expected_revision'] ?? null, "selection.$index.expected_revision", 1);
                $this->counter($item['sequence'] ?? null, "selection.$index.sequence", 0);
            }
        }
    }

    private function counter(mixed $value, string $key, int $minimum): void
    {
        if (! is_int($value) || $value < $minimum || $value > 9007199254740991) {
            throw ValidationException::withMessages([$key => $minimum === 0 ? 'Use a nonnegative safe integer.' : 'Use a positive safe integer.']);
        }
    }

    private function decimalId(string $id): int
    {
        abort_unless(ctype_digit($id) && (string) (int) $id === $id && (int) $id > 0, 404);

        return (int) $id;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function summaries(Configurator $owner, string $operation, array $input): array
    {
        $page = $input['page'] ?? 1;
        $search = $input['search'] ?? '';
        if (mb_strlen($search) > 255) {
            throw ValidationException::withMessages(['search' => 'Search may contain at most 255 characters.']);
        }
        $drafts = $operation === 'draft-summaries';
        $query = ($drafts ? ConfiguratorRuleWritingDraft::query() : ConfiguratorRule::query())->where('configurator_id', $owner->id);
        if ($search !== '') {
            $query->where('label', 'like', '%'.addcslashes($search, '%_\\').'%');
        }
        $columns = $drafts ? ['id', 'kind', 'label', 'origin_rule_id', 'revision', 'updated_at'] : ['id', 'kind', 'label', 'priority', 'is_active'];
        $rows = $query->orderBy($drafts ? 'created_at' : 'priority', $drafts ? 'asc' : 'desc')->orderBy('id')->offset(($page - 1) * 40)->limit(41)->get($columns);
        $more = $rows->count() > 40;

        return ['rows' => $rows->take(40)->map(function ($row): array {
            $data = $row->toArray();
            $data['id'] = (string) $data['id'];
            if (array_key_exists('origin_rule_id', $data) && $data['origin_rule_id'] !== null) {
                $data['origin_rule_id'] = (string) $data['origin_rule_id'];
            }

            return $data;
        })->values()->all(), 'page' => $page, 'has_more' => $more];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function openRule(User $actor, Configurator $owner, array $input): array
    {
        $id = $this->decimalId($input['rule_id']);
        abort_unless(ConfiguratorRule::where('configurator_id', $owner->id)->whereKey($id)->exists(), 404);

        return $this->editorResult(app(ConfiguratorRuleWritingDrafts::class)->openRule($actor, $owner, $id, $input['new_draft_id']));
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function editorResult(array $result): array
    {
        $draft = array_intersect_key($result['draft'], array_flip(['id', 'kind', 'origin_rule_id', 'source', 'label', 'description', 'revision', 'format_version', 'updated_at']));
        if ($draft['origin_rule_id'] !== null) {
            $draft['origin_rule_id'] = (string) $draft['origin_rule_id'];
        }

        return ['draft' => $draft, 'generated' => $result['generated'] ?? false, ...isset($result['acknowledgement']) ? ['acknowledgement' => $result['acknowledgement']] : []];
    }

    /** @param array<mixed> $selection @return array<string,mixed> */
    private function save(User $actor, Configurator $owner, array $selection): array
    {
        $result = app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection);

        return ['receipt_json' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'items' => array_map(fn (array $item): array => [...$this->editorResult($item), 'rule_id' => (string) $item['rule_id']], $result['items'])];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function capture(User $actor, Configurator $owner, array $input): array
    {
        $result = app(ConfiguratorRuleWritingReconciliation::class)->capture($actor, $owner, $input['id'], $input['expected_revision']);
        $payload = $result['snapshot']['payload'];

        return [...$this->editorResult($result), 'snapshot_json' => json_encode($result['snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'latest_source' => $payload['latest_source'], 'latest_label' => $payload['latest_rule']['label']];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function refreshSections(User $actor, Configurator $owner, array $input): array
    {
        $raw = $input['snapshot_json'];
        if (strlen($raw) > 8388608) {
            throw ValidationException::withMessages(['snapshot' => 'The displayed snapshot exceeds the 8 MiB limit.']);
        }
        try {
            $snapshot = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['snapshot' => 'Use the displayed Rule snapshot.']);
        }
        if (! is_array($snapshot) || ! str_starts_with(ltrim($raw), '{')) {
            throw ValidationException::withMessages(['snapshot' => 'Use the displayed Rule snapshot.']);
        }

        return $this->editorResult(app(ConfiguratorRuleWritingReconciliation::class)->updateDraft($actor, $owner, $input['id'], $input['expected_revision'], $snapshot, $input['sections'], $input['sequence']));
    }

    /** @param array<string,mixed> $data */
    private function response(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
