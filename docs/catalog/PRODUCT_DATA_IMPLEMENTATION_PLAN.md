# Product Data Foundation Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` to implement reviewed tasks in this session. Use `superpowers:subagent-driven-development` only if the operator chooses that execution method. Steps use checkboxes for tracking. This document authorizes no implementation, database mutation, commit or push.

**Goal:** Keep the current Product JSON storage while making Group filters/presets, card fields and Configurator rules consume the same exact non-Part facts, independently of the first legacy export layout.

**Architecture:** `products.properties`, `products.extra_data` and `products.parts` remain the authoritative storage. A shared resolver gives Properties and Extra data one field identity, preserves typed source values, and reports conflicting copies. A bound SQL projection/predicate adapter uses that same contract; catalog discovery remains cached by Group revision and Configurator evaluation receives facts in memory.

**Tech Stack:** PHP 8.4; installed Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Boost 2.10.2, Pest 5.3.0 and Filament Blueprint 2.4.0. Local MySQL 8.0.46; the default feature runner uses SQLite. Recheck installed versions before execution.

**Spec:** [SELECT_PREPOPULATION_AND_ITEM_DRAWER_REVIEW.md](research/SELECT_PREPOPULATION_AND_ITEM_DRAWER_REVIEW.md), sections 11–17, and [ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md](ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md), section 18. The operator selected **JSON plus a shared Product-data contract for planning** on 2026-10-10 and approved the conflict/value policies below on 2026-10-13. Storage alternatives remain comparison material, not implementation instructions.

**Readiness:** The domain choices are recorded and the foundation tasks are specified, including conflict blocking and saved-value validation. The implementation plan still awaits operator review and execution authorization. No task has been executed for this planning step.

## Global Constraints

- Preserve Product identity, legacy IDs, Group relation, status and existing availability rules. Keep Group and legacy IDs read-only in the Product editor.
- Keep all three Product JSON columns. No Product-fact table, property registry, shared Part model, storage backfill or dependency change belongs to this foundation.
- Properties and Extra data are eligible for filters, presets, cards and rules. Parts are Product/drawing positions and are excluded from that shared fact space.
- Preserve exact keys, literal strings, whitespace, leading zeroes, units/ranges and non-text JSON values. Do not parse units or infer numeric comparisons.
- Product inputs edit existing text values only. Keys cannot be added, deleted, renamed or reordered there; Product value suggestions remain off.
- Field-reference suggestions come from relevant Products plus saved references. An admin may type a future key before any Product contains it. Suggestions do not establish eligibility or create Product data.
- Retain saved Group filter values/labels and preset values when Products no longer contain them; new values must be observed in that Group's current catalog vocabulary. This is distinct from manually authored future keys and from free-text Product value editing.
- Conflicting non-Part copies contribute no resolved facet/card value. Only configuration using an active rule that references the conflicted field is blocked; unrelated configurations and Product visibility remain unchanged. Reject newly introduced write/import conflicts.
- Keep catalog filtering in its existing Group/branch context and leaf-Group snapshot contract. Admin filters across Groups are a separate workload; this plan adds no cross-Group catalog route or new admin Product-fact filter UX.
- Preserve cached Group discovery, atomic revision changes and the warm matched-card contract: two SELECTs, one Product SELECT and no SQL count. No database query belongs inside an individual engine condition.
- Retain current rule operators and default missing behavior: absent/null/non-text conditions are false, including negative comparisons; normal Boolean OR applies. Configurable missing-data modes are a separate approved follow-on, recorded below.
- Keep dashboard Territory/Application exclusion. Context-excluded and inactive rules must not cause new Product-data blocking.
- Preserve concurrent work. The current Product editor and other dirty files are not permission to stage an entire checkout. Publication needs fresh authorization and a reviewed explicit file list.
- Follow AGENTS.md and applicable `.ai/rules` if present. `.ai/rules` was absent at this planning checkpoint. Use Boost `search-docs` for implicated APIs, `database-schema` for schema and read-only `database-query` for inspection. Do not use production data to generate test/benchmark fixtures.

## Review Focus

1. Literal headings containing dots, quotes, backslashes, Unicode, leading/trailing spaces or numeric-looking keys must survive authoring, bound SQL and reload without becoming a JSON path or Laravel state path. Tasks 1–4 own these tests.
2. A key moved between Properties and Extra data, or present in both with different typed values, must never acquire an accidental bucket winner. Tasks 1, 3, 5 and 6 own the resolution/write/runtime cases.
3. An empty Group, a saved future key or a previously saved value disappearing from Products must not silently erase admin intent. Task 4 owns reference roundtrips and the approved retained-saved/observed-new value validation.
4. Warm snapshots, card responses and memoized suggestions must refresh after an atomic edit/import while unchanged or denied saves make no revisions, writes or notifications. Tasks 3–5 own these checks.
5. Forged editor keys, foreign Group/preset identities, unauthorized actions and detailed diagnostics must remain denied/isolated even when the visible picker accepts free text. Tasks 4–6 own these checks.

## Approved policies, 2026-10-13

| Decision | Operator-approved behavior | Enforcement |
| --- | --- | --- |
| A: existing conflicting copies of a non-Part key | Show an authorized admin conflict; exclude the fact from catalog facets/cards. Block configuration only when an active rule in the current evaluation scope references the conflicted key. Do not hide the Product or block unrelated configurations. Imports introducing conflicts fail review; new editor conflicts fail validation. | Shared resolver/query adapter excludes ambiguous facts; Task 6 guards rule references before settling or accepting an intent. No `CatalogAvailability` or Product status change. |
| B: saved preset/filter values with no current Product occurrence | Retain saved values and labels. New values must be observed; manual future values are not offered. Manual future **keys** remain valid, including a filter with an empty vocabulary. | Task 4 validates current Group vocabulary plus the original values of the same owned, locked row with its exact unchanged key. A preset still needs at least one observed or legitimately retained value. |

These answers close both domain questions. The concrete implementation choices below remain subject to review of this plan; recording a policy does not authorize execution.

## Scope and follow-on boundaries

This foundation produces a working application with the existing importer/editor and current default engine semantics, corrected to use the shared data contract. The following already accepted work remains distinct:

| Follow-on | Requirements preserved | Why it has a separate engineering plan |
| --- | --- | --- |
| Reviewed flexible legacy Import | Add only/Replace; preserve every column; map to Properties/Parts/Extra data; source headings stay keys; reviewed generated keys for blank/repeated headings; omitted fields survive Replace, explicit blanks clear; compact review plus expandable changes; reusable reviewed profiles; source/mapping snapshots revalidated on Apply | Needs profile persistence/versioning, upload/layout review and preview/apply lifecycle specifications. A shared resolver is not an import-review UI or an immutable review snapshot. |
| Global missing-data settings | Global only, initially Condition false; false/skip whole rule/unavailable modes; negative-comparison, strict/normal OR, blocking-threshold and blank-text controls; lock-only blocked UI, previous valid choices, quiet agent false/skip modes and admin diagnostics; cache policy once into engine input | Needs complete tri-state truth evaluation, intent transitions, settings cache invalidation and blocked-state preservation. Adding key suggestions must not silently introduce those engine policies. |
| Storage/performance reconsideration | Existing JSON, fact rows and one owned JSON record remain comparison candidates; benchmark representative varied data and separate workloads | Group counts and query counts do not prove latency or justify a migration. No benchmark or storage switch is authorized by route selection for this plan. |

Do not call these follow-ons implemented when the foundation lands. Existing comparison examples remain reusable; no new storage example is needed to illustrate the selected JSON route.

## Models and storage

### Product

**Location:** `app/Models/Product.php`.

No schema, relationship or status change. Keep the existing casts/public model interface. Read raw JSON where preserving nested object-versus-array shape matters; do not let an unrelated text edit rewrite structured values through a lossy cast roundtrip.

| Column | Foundation meaning |
| --- | --- |
| `properties` | Product-owned source/display bucket of facts |
| `extra_data` | Product-owned source/display bucket of facts, equally eligible for consumers |
| `parts` | Product-owned drawing positions; actual saved keys determine rows |

Absence and a present JSON null remain distinct in the reader, although the current default engine treats both as missing. An empty string is present; catalog facets/cards omit it as today. Non-text values remain present/read-only and do not become string-comparable automatically.

### Saved references

**Locations:** `app/Models/GroupFilter.php`, `app/Models/SubGroup.php`, `app/Models/RuleCondition.php` and their existing tables.

Keep `property_key` and `ConditionSource::ProductProperty` for persisted compatibility; change their meaning/documentation to an exact resolved non-Part fact key. The existing reference length is 100 characters. Preserve all source keys in Product JSON; authoring a longer reference must fail visibly rather than truncate or rename it. Increasing that reference limit requires its own reviewed schema adjustment.

The local `property_key` columns are `varchar(100) COLLATE utf8mb4_unicode_ci`; RuleCondition's is nullable, the other two are not. The GroupFilter `(group_id, property_key)` unique index currently conflates case and trailing-space variants. Proposed foundation migration: change only these reference columns to MySQL `utf8mb4_0900_bin`, keeping length/nullability/indexes; SQLite retains exact BINARY comparison. This is a **reference collation migration**, not a Product storage migration. Verify target DB compatibility before applying it. Rollback must refuse, without modification, if now-valid case/space-distinct GroupFilter keys would collide under the old collation. [MySQL binary collations and padding](https://dev.mysql.com/doc/mysql/8.0/en/charset-binary-collations.html).

### Shared interfaces

Create in existing `app/DTO` and `app/Services` directories:

```php
namespace App\DTO;

final readonly class ProductFacts
{
    /**
     * @param array<int|string, mixed> $values Unambiguous facts, including null/non-text.
     * @param array<int|string, array{properties: mixed, extra_data: mixed}> $conflicts
     * @param array<int|string, list<'properties'|'extra_data'>> $origins
     */
    public function __construct(
        public array $values,
        public array $conflicts,
        public array $origins,
    ) {}
}
```

```php
namespace App\Services;

use App\DTO\ProductFacts;
use Closure;

class ProductData
{
    public static function validReferenceKey(mixed $key): bool;
    public static function validateReferenceKey(string $attribute, mixed $value, Closure $fail): void;
    /** @return array<int|string, mixed> */
    public static function decodeBucket(?string $json): array;
    public static function identicalValue(mixed $first, mixed $second): bool;
    public static function resolve(array $properties, array $extraData): ProductFacts;
    /** @return array{properties: array, parts: array, extra_data: array} */
    public static function replaceMapped(array $existing, array $supplied): array;
}
```

These are signatures, not stub files to create. Implement the rules in Task 1. Do not introduce a container binding for the stateless resolver or put storage queries into it.

## Resources, fields and authorization

Existing resources/pages only; no new resource, relation manager, dashboard widget or navigation item.

| Surface | Exact changes |
| --- | --- |
| Group Filters/Presets/Presentation and Group editors embedded in count drawers | Observed/saved key suggestions, manual future keys, server save validation and retained-saved/observed-new values |
| Configurator rule form | Same literal key input, suggestions from currently assigned leaf Groups, no first-export eligibility list; preserve the dedicated workspace and existing condition layout |
| Group batch presentation editor | Allow the same ordered manual/suggested card keys; reviewed preview/apply contract remains unchanged |
| Product right-pane editor | Existing fixed keys/no suggestions; shared conflict validation and structured-value preservation; authorized repair guidance |
| Catalog cards/details and Product configurator/Preview | Shared facts; actual Parts keys; rule-scoped conflict blocking and authorized diagnostics |

### Literal field-reference input

**Component:** `Filament\Forms\Components\TextInput`.

**Docs:** [datalist](https://filamentphp.com/docs/5.x/forms/text-input#autocompleting-text-with-a-datalist), [trimming](https://filamentphp.com/docs/5.x/forms/text-input#trimming-whitespace).

**Validation:** `required`, `string`, `max:100`, `ProductData::validateReferenceKey(...)`; distinct filters/card keys use `distinct:strict`. Reject empty/whitespace-only keys, invalid UTF-8 and NUL. Preserve permitted leading/trailing whitespace exactly. Do not slugify, lowercase, Unicode-normalize or interpret dots as nesting.

**Config:** `->label('Product field')->trim(false)->required()->maxLength(100)->datalist($suggestions)`; filter/preset fields use `->live(onBlur: true)` when their value UI depends on the chosen key. Rules keep their existing visibility/dehydration based on condition source.

**Imports for reactive callbacks:** `Filament\Schemas\Components\Utilities\Get`, `Filament\Schemas\Components\Utilities\Set`, `Livewire\Component`. Use the installed `$old` state utility where the key changes. A programmatic `Set` does not automatically execute the other field's updated hook; invoke shared draft-building logic explicitly.

### Ordered card fields

**Component:** `Filament\Forms\Components\Repeater`, constructed through `App\Filament\Resources\FormOrderControls::make()`.

**Docs:** [simple repeaters](https://filamentphp.com/docs/5.x/forms/repeater#simple-repeaters-with-one-field).

**Validation:** list of valid exact keys, `distinct:strict`; remove the maximum tied to the first export's property count.

**Config:** `->simple(TextInput::make('key')->trim(false)->required()->maxLength(100)->datalist($suggestions))->defaultItems(0)->columnSpanFull()`. The simple repeater dehydrates to the existing flat `list<string>`; do not change `result_settings.card_properties` storage. Reuse both existing order-control triggers. Empty still means the Group filter keys in configured order.

Native TagsInput is unsuitable for these exact keys: installed `forms/resources/js/components/tags-input.js::createTag()` unconditionally trims the new tag. A PHP `trim(false)` does not remove that JS behavior. No vendor override or custom JS field is needed for the proposed TextInput/simple-repeater route.

### Retained fast controls

Keep the Group's existing `Filament\Forms\Components\Select::make('filter_properties_to_add')->multiple()->searchable()->dehydrated(false)->live()` for batch-adding observed/saved suggestions. **Docs:** [multiple selects](https://filamentphp.com/docs/5.x/forms/select#multi-select). It is a transient convenience input, not the definition of valid fields. Individual filter rows can add a manual future key. Remove only its first-export 18-field guard and the corresponding repeater `maxItems(18)`.

Preset values remain `Filament\Forms\Components\Select::make('allowed_values')->multiple()->searchable()->required()->minItems(1)` with exact current-plus-owned-saved options. **Validation:** nonempty list of distinct literal strings; only observed or legitimately retained values are accepted. No create-option/free-text entry is offered. Filter value rows retain their existing labels/order controls, with value identity fixed to that same allowed set. A retained value may be labeled as having no current matches; its label may still be edited or the row explicitly removed. A future key with no observations can define a filter, card field or rule, but cannot create a new arbitrary preset value.

### Existing actions

Group/Product Save stays on the current editor footer; fields do not directly write JSON or definitions. The Group batch action remains `Filament\Actions\BulkAction` in `Filament\Actions\BulkActionGroup`, authorized through `manage-catalog`, in its existing slide-over. **Docs:** [actions](https://filamentphp.com/docs/5.x/actions/overview). Visibility continues to follow existing resource authorization. Preview builds the complete selected intent; Apply reauthorizes, validates its reviewed token/current state and atomically persists. A future typed key is not permission to bypass preview or row ownership.

All authoring/suggestion/conflict-detail paths require `Gate::forUser($actor)->authorize('manage-catalog')`. Public/dashboard readers keep existing availability/authentication routes; no new admin diagnostics endpoint is exposed to agents. Detailed conflicts remain server-owned until rendered in an authorized admin/Preview context.

## File map and implementation tasks

### Task 1 — exact fact resolution and lossless JSON handling

**Create:** `app/DTO/ProductFacts.php`, `app/Services/ProductData.php`.

**Test:** `tests/Unit/ProductDataTest.php`.

**Produces:** the shared interfaces above, used by query, authoring, writers and engine loading.

- [ ] Scaffold with `php artisan make:class DTO/ProductFacts --no-interaction`, `php artisan make:class Services/ProductData --no-interaction` and `php artisan make:test --pest ProductDataTest --unit --no-interaction`. Check names do not already exist before running them.
- [ ] Add pure contract tests, then run `php artisan test --compact tests/Unit/ProductDataTest.php` and confirm the missing implementation is the reason for failure.

```php
use App\Services\ProductData;

test('one exact non-Part key works in either bucket without changing its text', function () {
    $facts = ProductData::resolve(
        ['Model' => 'D-060', '01' => ' 010 PSI '],
        ['Material' => 'Brass', 'model' => 'case distinct'],
    );

    expect($facts->values)->toBe([
        'Model' => 'D-060', '01' => ' 010 PSI ',
        'Material' => 'Brass', 'model' => 'case distinct',
    ]);
    expect($facts->conflicts)->toBe([]);
});

test('conflicting copies never choose a bucket or coerce source types', function () {
    $facts = ProductData::resolve(['Model' => '01', 'N' => 0], ['Model' => '1', 'N' => '0']);

    expect($facts->values)->toBe([]);
    expect($facts->conflicts)->toBe([
        'Model' => ['properties' => '01', 'extra_data' => '1'],
        'N' => ['properties' => 0, 'extra_data' => '0'],
    ]);
});
```

- [ ] Implement one-bucket and identical-two-bucket resolution, origins and conflict exclusion. Equality is type-strict for scalars; nested object members compare by exact key/value irrespective of object member order, while array element order and object-versus-array identity remain significant. Structured values remain non-comparable by current text consumers.
- [ ] Decode raw JSON with objects preserved inside the root key map; do not turn nested `{}` into `[]`. SQL NULL means an empty bucket. Unsupported scalar root shapes produce an explicit repair diagnostic rather than being treated as an ordinary field map.
- [ ] Implement `replaceMapped()`: process only supplied keys, preserve omitted keys, retain supplied blanks, and remove a replaced non-Part key's previous copy in the other bucket. Parts merge by Product-owned position. Reject ambiguous mappings which supply one non-Part key to both destination buckets. Do not regenerate or rename keys.

```php
$updated = ProductData::replaceMapped(
    ['properties' => ['Model' => 'old', 'Keep' => 'present'], 'parts' => ['Part10' => 'Bronze'], 'extra_data' => []],
    ['properties' => [], 'parts' => [], 'extra_data' => ['Model' => '']],
);

expect($updated)->toBe([
    'properties' => ['Keep' => 'present'],
    'parts' => ['Part10' => 'Bronze'],
    'extra_data' => ['Model' => ''],
]);
```

- [ ] Add boundary cases for null versus absence, empty/whitespace text, numeric keys, quoted/dotted/backslash keys, composed/decomposed Unicode, nested objects/arrays and unchanged typed values. Run this file again; it must pass before consumers adopt the contract.

### Task 2 — exact saved-reference comparison

**Create via Artisan:** one migration named `preserve_exact_product_field_references` in `database/migrations/` with its generated timestamp.

**Modify tests:** `tests/Feature/Catalog/CatalogSchemaTest.php`; MySQL wrapper `tests/Feature/Catalog/MySql/CatalogSchemaTest.php`.

**Consumes:** `ProductData::validReferenceKey()` and the existing 100-character references.

**Produces:** persisted field identity consistent with PHP/JSON; no Product JSON rewrite.

- [ ] Add a MySQL behavior test creating `Model`, `model` and `Model ` GroupFilter references in the same Group and reloading all three exact keys. A duplicate exact `Model` must still fail uniqueness. SQLite tests cover the same application outcome.
- [ ] Run the narrow schema tests on the guarded disposable MySQL connection and confirm the present collation causes the variant-reference failure.
- [ ] Scaffold `php artisan make:migration preserve_exact_product_field_references --no-interaction`. Preserve the three columns' definitions, changing only MySQL collation:

```php
Schema::table('group_filters', function (Blueprint $table): void {
    $table->string('property_key', 100)->collation('utf8mb4_0900_bin')->change();
});
Schema::table('sub_groups', function (Blueprint $table): void {
    $table->string('property_key', 100)->collation('utf8mb4_0900_bin')->change();
});
Schema::table('rule_conditions', function (Blueprint $table): void {
    $table->string('property_key', 100)->nullable()->collation('utf8mb4_0900_bin')->change();
});
```

- [ ] Guard by driver; confirm MySQL supports the target collation. Before rollback, detect GroupFilter collisions using the old collation and refuse if any exist. Never delete/merge references to make rollback succeed. Existing SQLite BINARY columns need no conversion.
- [ ] Run the schema tests again, including rollback refusal with `Model`/`model` present. Execution approval for this plan does not authorize applying the migration to production.

### Task 3 — bound queries, snapshots and matched cards

**Create:** `app/Services/ProductFactQuery.php`.

**Modify:** `app/Services/CatalogDiscovery.php`, `app/Services/BuildCatalogSnapshot.php`, `app/DTO/CatalogSnapshot.php`, `app/Services/CatalogSnapshots.php`, `app/Services/CatalogPolicy.php`, `app/Services/CatalogCards.php`, `app/Services/CatalogCardDisplay.php`, `resources/views/components/catalog/product-card.blade.php`, `resources/views/components/catalog/card-chunk.blade.php`.

**Tests:** new `tests/Feature/Catalog/ProductDataContractTest.php` with `tests/Feature/Catalog/MySql/ProductDataContractTest.php` wrapper; extend `tests/Feature/Catalog/CatalogSnapshotTest.php` and `CatalogDiscoveryTest.php`.

**Interfaces:**

```php
// App\Services\ProductFactQuery
use App\DTO\ProductFacts;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

public function project(Builder|EloquentBuilder $query, array $keys): Builder|EloquentBuilder;
public function facts(object $row, array $keys): ProductFacts;
public function whereTextIn(EloquentBuilder $query, string $key, array $values): EloquentBuilder;
```

`project()` appends indexed aliases for raw values and presence in both buckets; `facts()` decodes them and calls the shared resolver. `whereTextIn()` matches an unambiguous literal string with identical semantics. Never accept a user-provided SQL column or alias.

- [ ] Scaffold `php artisan make:class Services/ProductFactQuery --no-interaction` and `php artisan make:test --pest Catalog/ProductDataContractTest --no-interaction`; create the MySQL wrapper following the existing sibling wrappers. Do not overwrite existing names.
- [ ] Write fixtures where Model is only in Properties on Product A, only in Extra data on Product B, identical in both on C, conflicted on D, null on E and absent on F. Exact expected facet/filter IDs for B's value are B/C when their values match; D/E/F are excluded. Repeat with quote/backslash/dot/Unicode keys and `01` versus `1` values.
- [ ] Run `php artisan test --compact tests/Feature/Catalog/ProductDataContractTest.php tests/Feature/Catalog/CatalogSnapshotTest.php tests/Feature/Catalog/CatalogDiscoveryTest.php` and confirm the existing Properties-only behavior fails the new cases.
- [ ] Implement safe projections. MySQL uses bound encoded member paths and a separate presence expression:

```php
$path = '$.'.json_encode($key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$query->selectRaw('JSON_EXTRACT(properties, ?) as fact_p_'.$index, [$path]);
$query->selectRaw("JSON_CONTAINS_PATH(properties, 'one', ?) as fact_p_present_".$index, [$path]);
```

Repeat only for the fixed `extra_data` column. Index aliases are integers generated by the adapter. Do not concatenate the key into SQL. SQLite uses scalar `json_each(properties)`/`json_each(extra_data)` lookup by **bound literal `key = ? COLLATE BINARY`**, with `EXISTS` for presence. Encode its typed cells as JSON (`true`/`false`/`null`, intact arrays/objects, and quoted text) before calling the shared decoder; quoting a Boolean's numeric SQLite value would lose its type. [Laravel bound raw selects](https://github.com/laravel/docs/blob/13.x/queries.md#selectraw), [MySQL JSON functions](https://dev.mysql.com/doc/refman/8.0/en/json-search-functions.html), [SQLite json_each types](https://www.sqlite.org/json1.html#the_json_each_and_json_tree_table_valued_functions).
- [ ] Build text predicates with bindings for every key/path/value. Only these cases can match: sole Properties string, sole Extra data string, or two present identical strings. A present null/non-text/conflicting other copy forbids silently using the string copy. Compare literal text under MySQL `utf8mb4_0900_bin`/SQLite BINARY, preserving case and trailing spaces.
- [ ] Replace fixed key membership in discovery, snapshot building and result settings with key syntax validation. Keep saved future references. Vocabulary/diagnostics read selected keys together; no per-Product query or whole Parts/description payload. Keep first-Product ordering of vocabulary and the existing matching/reconciliation contracts.
- [ ] Increment `CatalogSnapshot::SCHEMA` from 1 to 2 and include that version in the cache key namespace so an old deployment cannot reuse a Properties-only dataset at the same Group revision. Keep plain-array cache storage, lock ownership/retry/revision checks and no rebuild inside a read transaction. Test a schema-1 cache entry with an unchanged Group revision.
- [ ] Cards select matched identity plus **only configured fact projections** in the existing single Product SELECT. Resolve once per row and pass an explicit `factsByProductId` map to comparison and Blade; do not populate `properties` with a fabricated merged write authority. `CatalogCardDisplay::compare()` gains an optional trailing fact-map argument; direct existing callers resolve from their provided Product JSON. Propagate the map through `resources/views/components/catalog/card-chunk.blade.php` as well.
- [ ] Test complete matching-set comparisons, escaped labels/text, future empty keys, schema invalidation and warm two-SELECT/no-count behavior. Conflict diagnostics stay server/admin-only and conflicted cells contribute no facet/card value; unaffected fields still match and render. Run both database variants, retaining current revision/availability/lock tests.

### Task 4 — observed/saved suggestions and future-key authoring

**Create:** `app/Services/ProductFieldSuggestions.php`.

**Modify:** `app/Filament/Resources/Groups/Schemas/GroupForm.php`, `app/Filament/Resources/Groups/Pages/EditGroup.php`, `app/Actions/SaveGroupSettings.php`, `app/Actions/BatchCatalogChanges.php`, `app/Filament/Resources/BatchEditActions.php`, `app/Filament/Resources/Configurators/Schemas/ConfiguratorRuleForm.php`, `app/Services/ConfiguratorDefinitionCompiler.php`.

**Tests:** `tests/Feature/Catalog/GroupSettingsTest.php`, `tests/Feature/Filament/BatchCatalogActionsTest.php`, `tests/Feature/Filament/ConfiguratorRulesTest.php`, `tests/Unit/ConfiguratorCompilerTest.php`, `tests/Feature/Filament/ItemDrawerEditorsTest.php` and `ItemListDrawerTest.php` for their existing Group/Presentation editor routes.

**Interfaces:**

```php
// App\Services\ProductFieldSuggestions; both entry points authorize manage-catalog.
public function forGroup(User $actor, int $groupId, array $savedKeys = []): array;
public function forConfigurator(User $actor, int $configuratorId, array $savedKeys = []): array;
```

Return `list<string>` exact keys. Group scope is that Group's Products; Configurator scope is Products in its currently assigned leaf Groups. Include inactive Products for **authoring suggestions only**, never as an availability grant or catalog vocabulary value. Merge saved references and deduplicate strictly. Query only non-Part key lists, memoized for the current request/scope; new requests must observe Product edits/imports/assignment changes. Do not add a permanent cache with no invalidation owner.

- [ ] Scaffold `php artisan make:class Services/ProductFieldSuggestions --no-interaction`, checking for an existing class first.
- [ ] Write the no-Products scenario: an admin saves filter/card/rule references for `Future.Field`, reloads them unchanged, then edits a Product containing that key. The existing reference must start working without being rewritten. Unauthorized actor, foreign preset/filter row and invalid key tests assert no writes/revisions/notifications.
- [ ] Run the corresponding focused test files and confirm the fixed import allowlist is responsible for failures.
- [ ] Install the field specs above. Remove fixed `propertyKeys()` usage and first-export count limits in server validation, Group form controls, batch presentation validation and compiler. Keep current compiler condition/operand limits and rule-source enum names. Use the same key validator for all authoring paths:

```php
'settings.filters.*.property_key' => ['required', 'string', 'max:100', 'distinct:strict', ProductData::validateReferenceKey(...)],
'settings.result_settings.card_properties.*' => ['required', 'string', 'max:100', 'distinct:strict', ProductData::validateReferenceKey(...)],
```

The compiler uses `ProductData::validReferenceKey($property)` without database access and reports `Enter a valid Product field key.` rather than requiring a registered property.
- [ ] Implement the approved value allowance in both hydration and the authoritative save action. Allowed values are current vocabulary plus original values belonging to the **same owned, locked row and exact unchanged key**. For a GroupFilter, retained values are the strict union of its persisted `value_order` and the keys of `value_labels` (convert PHP integer map keys back to their JSON string spelling); for a preset, use its persisted `allowed_values`. Do not accept a client-provided saved flag, draft cache, foreign row ID or another row's values as proof. A changed key starts with that new key's current vocabulary, without inheriting old-key values. Compare literal strings strictly, retaining whitespace/zero prefixes. Labels/order survive hydration and unchanged saves; public controls expose only current observed values. Explicit admin removal remains possible.
- [ ] Test a value that disappears from Products: the same row/key reloads with its saved value/label/order, an unchanged save makes no revision, and changing its label is accepted. A new currently observed value in the same Group is accepted; an arbitrary value, a value observed only in another Group, a forged ID and stale values carried onto a changed key fail atomically. Test explicit removal, an empty future-key filter and a new preset with no observed/retained values. Direct action calls must enforce the same rules as the form.
- [ ] Preserve row IDs, value labels/order and unsaved text on reactive key changes. In EditGroup keep a `#[Livewire\Attributes\Locked]` transient map keyed by component state path and exact key. Capture the old row draft through `$old` before selecting the new vocabulary; returning to the earlier key restores it and merges newly observed values. This draft cache is not trusted as permission to save values. Clear it only after a successful authoritative refill, not after a failed save. Test actual `->set()` updates key A → B → A, not merely a final `fillForm()` array.
- [ ] Replace `__moving_ID` temporary filter keys with collision-free temporary keys checked against all locked existing/submitted keys. Preserve stable IDs during a two-filter key swap; never overwrite another admin-authored `__moving_ID` key. Use the same transaction and `CatalogRevisions::batch()` path.
- [ ] Add a `field-keys` batch field type for card fields, rendered as the same simple repeater, with strict key validation rather than options membership. Both Preview and Apply retain current token/fingerprint/authorization checks. For multi-Group selections, suggestions are the union for selected Groups; do not require a key to occur in every Group.
- [ ] Test 19 valid filters and card-key lists longer than the first export's 18 keys, exact case/space variants, unchanged saves, manual future keys, denied batch Apply and embedded Group editor save/refill. Run focused tests; execute FilaCheck/Pint at the final verification task.

### Task 5 — Product saves and current import share the write contract

**Modify:** `app/Actions/SaveCatalogProduct.php`, `app/Services/ProductEditorData.php`, `app/Filament/Resources/Products/Pages/EditProduct.php`, `app/Filament/Resources/Products/Schemas/ProductForm.php`, `app/Actions/ImportCatalogProducts.php`.

**Tests:** `tests/Feature/Filament/ProductEditingTest.php`, `tests/Feature/Catalog/CatalogImportTest.php` and its existing MySQL wrapper.

**Consumes:** `ProductData::decodeBucket()`, `resolve()` and `replaceMapped()`.

**Produces:** one non-Part identity across existing editor/import writes; no flexible parser/profile UI yet.

- [ ] Add a Product editor regression where editing one text sibling preserves a nested empty object, array, Boolean, number and numeric-key root object. Assert the raw stored JSON shape and literal text on a second unchanged save, not only the model's array cast.
- [ ] Add tests for new-conflict rejection, identical duplicates, repair by aligning existing copies, unrelated edits on a pre-existing conflict and no-op saves. The existing conflict pair may remain unchanged while unrelated editable content is saved; a changed pair must resolve rather than introduce a different conflict. Failure preserves the draft, database attributes, revision and notification state.
- [ ] Run `php artisan test --compact tests/Feature/Filament/ProductEditingTest.php tests/Feature/Catalog/CatalogImportTest.php` and confirm new type/conflict cases fail for the expected reason.
- [ ] Hydrate editor rows from lossless raw bucket decoding. Apply fixed-key value changes to that same decoded source and preserve top-level/nested object shapes when assigning changed JSON. The latest authoritative locked Product, not client flags/labels, decides existing keys and read-only types. Keep the stale fingerprint, core-field allowlist and Group-before-Product lock order.
- [ ] Use `replaceMapped()` when the current canonical CSV importer prepares bucket replacements, so review and Apply calculate identical moved-key changes. Preserve existing source/map/parents hashes, authorization, lock/transaction/revision boundaries, unchanged rows and status choices. The fixed 67-column parser remains a compatibility adapter; its `propertyKeys()` method ceases to define the application's field model.
- [ ] Show conflict keys/source buckets through authorized existing Product form hints/validation and Preview diagnostics; do not send paired conflict values through agent-facing diagnostics. Do not silently clean up duplicate keys or mutate statuses. Test a source/map hash change between review and Apply and an atomic import rejection with no partially updated Product/Group/revision.
- [ ] Run focused editor/import tests on SQLite and the guarded MySQL variant. Verify changing an Extra data fact refreshes cached vocabulary/cards and engine input on the next request.

### Task 6 — server-owned engine facts and complete Product data display

**Modify:** `app/Services/ConfiguratorDefinitionLoader.php`, `app/DTO/ConfiguratorEvaluationInput.php`, `app/DTO/ConfiguratorEvaluationResult.php`, `app/Services/ConfiguratorEngine.php`, `app/Livewire/Catalog/ConfiguratorPreview.php`, `app/Livewire/Catalog/ProductConfigurator.php`, `resources/views/livewire/catalog/product-configurator.blade.php`, `resources/views/livewire/catalog/configurator-preview.blade.php`, `resources/views/components/catalog/configuration-fields.blade.php`, `resources/views/components/catalog/configuration-result.blade.php`, `resources/views/components/catalog/product-facts.blade.php`.

**Tests:** `tests/Feature/Catalog/ConfiguratorDefinitionTest.php`, `ConfiguratorIntegrationTest.php`, `ConfiguratorRuntimeTest.php`, `tests/Unit/ConfiguratorEngineTest.php` and applicable existing `tests/Unit/Configurator/ConfiguratorEngineTest.php` cases.

**Consumes:** `ProductFacts` and syntactically valid saved references. **Produces:** resolved facts on every loader path before pure engine evaluation, with unchanged current comparison operators/default missing behavior.

**Blocked-result contract:** append `public bool $isBlocked = false` after the existing optional Result constructor arguments. Existing non-conflict paths keep their current behavior. `state()` keeps its version-1 shape and stores only Configurator/context/selection IDs; it never stores fact values, conflicts, diagnostic references or the blocked flag. Every Result copy, including the engine's `diagnostic()` helper, must preserve `isBlocked`.

The engine gains two private methods:

```php
/**
 * @param array<int|string, array{properties: mixed, extra_data: mixed}> $conflicts
 * @return list<array{property_key: string, rule_ids: list<string>}>
 */
private function conflictedRuleReferences(ConfiguratorDefinition $definition, array $conflicts): array;

/**
 * @param array<string, string> $context
 * @param list<array<string, mixed>> $diagnostics
 * @param list<array{property_key: string, rule_ids: list<string>}> $references
 */
private function blockedResult(ConfiguratorEvaluationInput $input, array $context, array $diagnostics, array $references): ConfiguratorEvaluationResult;
```

`ConfiguratorEvaluationResult::forCatalog(): self` provides the agent-facing projection: copy all normal Result fields and the blocked flag, remove `references`/other metadata from `product_fact_conflict` diagnostics, retain their generic message/code, and omit trace. Preserve the existing treatment of other diagnostic codes. The full protected server Result is retained for authorized Preview; neither raw conflict pairs nor their values are included in the new Result diagnostic in either context.

- [ ] Add a saved Product-property rule using `ExtraOnly` and an Extra data value, then load through the actual dashboard and authorized Preview entry points. Assert the intended hidden/disabled/presentation effect, not just that the input array contains the key. A future absent key and a non-text value keep the current false/negative/OR outcomes.
- [ ] Add the conflict lifecycle tests before implementing the guard: an unused conflict allows normal code generation; an active referenced conflict locks configuration; inactive rules and dashboard-excluded public-context rules do not block. An identical typed copy is not a conflict. A referenced conflict inside a nested group still blocks when another OR branch is true or another AND condition is false: this approved guard checks active references, not whether missing-data truth evaluation would otherwise determine the result.
- [ ] Run affected compiler/definition/integration tests and confirm the old allowlist/Properties-only path causes the new failures.
- [ ] Resolve both raw non-Part buckets once after loading the Product. Keep the existing `ConfiguratorEvaluationInput::$properties` name for compatible callers, now documenting an unambiguous non-Part map. Append `public array $factConflicts = []` as a trailing constructor argument; propagate it explicitly when Preview copies the input. No facts/policy/conflict details come from browser runtime or selection intents.

```php
$facts = ProductData::resolve(
    ProductData::decodeBucket($product->getRawOriginal('properties')),
    ProductData::decodeBucket($product->getRawOriginal('extra_data')),
);
// Pass $facts->values as properties and $facts->conflicts as factConflicts
// on assigned, unassigned, disabled, dashboard and Preview loader paths.
```

- [ ] After existing definition/required-Attribute/default-Option availability checks and context sanitization, call `conflictedRuleReferences()` once, before `settle()` or intent handling. Walk all nested compiled condition groups for `ConditionSource::ProductProperty` and exact conflict-key identity, including presentation rules. Current evaluation scope means rules retained by the loader for this entry point; do not re-enable public-context rules it excluded. Skip inactive rules, deduplicate keys/rule IDs, and make no database calls. Conflict is a separate blocked reason, never a missing fact satisfying a negative comparison. Do not enable the follow-on missing-policy settings here.
- [ ] When references are present, return `blockedResult()` without evaluating rules, choosing defaults/fallbacks, applying presentation effects or accepting selection/context intents. Keep the current definition and sanitized context, `isBlocked: true`, `isComplete: false`, `configurationCode: null`, and empty trace. Attribute states use their base labels/help/options, `applicable: true`, no legal choices and only the definition's static hidden/disabled sets. The blocked flag locks interaction without changing domain status flags or claiming computed rule legality.
- [ ] Preserve prior server-confirmed selection/remembered IDs from the same Product/Configurator runtime when they still belong to the current Attribute and a statically selectable included Option (active and neither hidden nor disabled). Drop deleted/foreign/inactive references; do not manufacture a replacement. Retained choices are previous choices, not a claim that they remain legal under unevaluated rules. First-load blocked configuration has no selections/default code. Continue using the component's existing `#[Locked]` Product/runtime properties, loader state-version/Configurator checks and Preview reset on changing Product; no browser-supplied fact or extra persistent store is introduced.
- [ ] Append one `product_fact_conflict` diagnostic with generic message `Configuration is unavailable until Product data is repaired.` and server-only `references` containing the exact keys and affected rule IDs. `ProductConfigurator::render()` passes `result()->forCatalog()` to its view; keep the original Result protected. Preview continues to authorize every entry/action/render and uses the full Result, showing escaped exact keys and affected rule labels/IDs in its existing diagnostics pane. Do not put conflict entries into the attribute-indexed trace array or raw pairs into Livewire public state.
- [ ] Disable the native configuration `<fieldset>` with `@disabled($result->isBlocked)` and disable only Preview's Attribute Select/ToggleButtons with `->disabled($result->isBlocked)`. Product selection and Refresh remain available. In `configuration-result`, show the short blocked message before the incomplete/code branches, even when ordinary diagnostics are hidden. Initial blocked controls use a repair-needed placeholder; retained controls show their previous choice. Preview labels them as locked instead of reporting zero legal Options as a computed rule result. Server rejection remains mandatory even when a forged action bypasses disabled controls.
- [ ] Test a valid prior selection followed by a conflicting edit: the selection survives, a different requested Option is rejected, controls lock and the old code disappears. Repair the Product and Refresh: normal rule evaluation resumes and revalidates the retained choice, selecting a legal default/fallback only then. Also test first-load blocking, changed/deleted IDs, forged runtime updates/intents, both native and Preview controls, the enabled Product picker/Refresh, generic agent output, no conflict metadata in the agent-facing diagnostic or serialized runtime, escaped authorized admin references and denial to non-admin Preview callers. Existing Product information display is separate from these diagnostic assertions.
- [ ] Keep the engine query-free. A regression test starts query logging after the input has been loaded, evaluates multiple selection intents and asserts no queries. Product/group loading costs are reported separately and Group size introduces no per-evaluation Group scan.
- [ ] Product details read selected non-Part facts from the resolver so a Group filter key located in Extra data is visible correctly. Preserve source bucket labels for remaining information; do not fabricate a fourth editable bucket. Render Parts from the Product's actual saved key/value rows, removing `range(1, 28)` and phantom blank positions. Remove the first-100-source-fields assumption where it silently hides existing data; keep existing escaped/limited value rendering.
- [ ] Test Part10 on two Products as separate positions, an actual Part29/custom position being visible, a missing Part1 not generating a phantom row, Extra data filter facts in details, XSS escaping, Preview authorization/foreign Product assignment, dashboard context exclusion and current status/direct-route behavior. Run affected tests with no numeric/unit normalization introduced.

### Task 7 — acceptance, measurement and publication boundary

**Modify documentation after implementation:** this plan's checkboxes/checkpoint, `docs/catalog/ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md`, `docs/catalog/research/SELECT_PREPOPULATION_AND_ITEM_DRAWER_REVIEW.md`.

- [ ] Refresh Git status and identify this phase's exact changed files/hunks. Review shared-file changes against the preceding Product editor/concurrent sessions; preserve anything outside the authorized phase.
- [ ] Run each task's focused tests after its last change. Run `vendor/bin/pint --dirty --format agent` for PHP, then inspect its diff. Run `vendor/bin/filacheck --fix --dirty` after Filament changes, preserve required `tableFilters` state paths and address actual remaining violations. Never enable the incompatible URL-state fixer to obtain a clean scan.
- [ ] Run the full default suite with `php artisan test --compact` only in the configured isolated test environment at acceptance. Run the affected MySQL wrappers only through `tests/bootstrap-mysql.php`'s dedicated `configurator_catalog_test` connection/user/host/port guards. No test runner or `migrate:fresh` may target the development or production database.
- [ ] Run `npm run build` if frontend assets were changed. Blade-only changes still need actual browser acceptance. Do not install a browser-test dependency or start a Herd server.
- [ ] Visually review Group Filters/Presets/Presentation in the main right pane and embedded count drawer, Group batch card fields, rule key input, Product editor/details and Preview. Verify typed future keys, exact headings, empty vocabularies, retained drafts, keyboard use and no new wrapping/density regressions. Use Boost `get-absolute-url` for URLs and recent `browser-logs` for errors.
- [ ] Exercise the approved policies visually: retain/remove a no-longer-observed preset/filter value; reject a new unobserved value; block a used conflicting key with prior choices visible; confirm an unused conflict leaves configuration usable; repair and Refresh without losing unrelated editor drafts. Review agent and authorized Preview output separately.
- [ ] Confirm the snapshot/card query regression tests and record observed SQL/server measurements separately from authenticated browser click-to-settle timings. Existing size observations are not benchmarks. If an isolated representative measurement is approved, compare 100/200/1,000/3,000 Products per Group with varied keys/cardinalities and identical expected matches. Do not build fixtures in an active database or promise an unmeasured latency target.
- [ ] Produce a reviewable diff and report actual tests, browser scope, failures/limitations and remaining follow-ons. Do not commit, push, deploy or apply production migrations without the operator's next explicit authorization; no `git add -A`, reset or stash is part of this plan.

## Execution setup and commands

At execution, activate `laravel-best-practices`, `filament-development`, `filament-forms-ux-audit`, `livewire-development` and `testing-best-practices`. Activate `filament-performance-audit` for query/cache acceptance. Use `alpine-js`/`tailwindcss-development` only if the implementation actually changes those layers. Read each selected skill and applicable rule files before editing. Use installed APIs and sibling conventions; no framework upgrade belongs here.

New class/test/migration commands are listed in their owning tasks. No new Filament resource/model scaffold is needed. Feature tests inherit the existing Pest `TestCase`/`RefreshDatabase` convention; pure resolver tests use the unit runner. The browser package is not installed and is not required by this plan.

Use the existing `phpunit.mysql.xml` configuration for focused MySQL runs, for example `vendor/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Catalog/MySql/ProductDataContractTest.php`. Its bootstrap rejects unsafe connection overrides/cached configuration. The accepted disposable target is `APP_ENV=testing`, database/user `configurator_catalog_test`, host `127.0.0.1`, port `3307`; confirm the resolved target before running. Credentials stay in the operator's configured test environment, not this document. Existing MySQL wrappers contain their own projection/concurrency cases; update assertions that prohibited any `extra_data` extraction to prohibit whole-column/unrelated data reads instead, preserving the single Product SELECT and exact outcomes.

Useful documentation verified through Boost and installed source:

- [Filament 5 field utility injection](https://filamentphp.com/docs/5.x/forms/overview#field-utility-injection), [simple repeater state](https://filamentphp.com/docs/5.x/forms/repeater#simple-repeaters-with-one-field), [disabled fields](https://filamentphp.com/docs/5.x/forms/overview#disabling-a-field), [testing](https://filamentphp.com/docs/5.x/testing/overview).
- [Livewire 4 locked properties](https://livewire.laravel.com/docs/4.x/attribute-locked), [action security](https://livewire.laravel.com/docs/4.x/security). Locked state blocks client changes; server assignments still require validation and authorization.
- [Laravel 13 strict array validation](https://github.com/laravel/docs/blob/13.x/validation.md#rule-distinct), [bound query expressions](https://github.com/laravel/docs/blob/13.x/queries.md#raw-expressions), [cache/atomic locks](https://github.com/laravel/docs/blob/13.x/cache.md).
- Installed `EditRecord::save()` authorizes before validation; `getState()` validates before `beforeSave`, followed by the custom `handleRecordUpdate()`/`afterSave()` path. Group atomic actions remain the persistence enforcement point. A failed action must not reach success notifications/authoritative refill.
- Installed Repeater simple-field dehydration preserves the flat value-list contract. Installed `HasState::callAfterStateUpdatedHook()` supplies `$old`; programmatic `Set` calls do not imply another field's update hook ran.

## Planning checkpoint and review

- Confirmed by source/Boost: current JSON columns, first-export consumer dependencies, reference-column collations/nullability, existing save/lock/cache boundaries, literal input/repeater APIs and installed package versions.
- Observed local storage sample: 501 Products; 33 maximum non-Part keys; 989 maximum serialized Properties-plus-Extra-data bytes. This homogeneous source is not coverage of varied exports or a speed result.
- Operator scale estimate: roughly 50 leaf Groups, most 50–200 Products, several around 100–1,000 and a possible 3,000 in one Group. No total or hard limit is inferred.
- Changed for this planning step: documentation only. No runtime code/schema/data/test fixtures were changed and no new tests/benchmarks were run.
- Both domain choices are approved as of 2026-10-13: rule-scoped conflict blocking and retained-saved/observed-new values. Tasks 4 and 6 now specify their enforcement, UI lifecycle, diagnostics and regression cases.
- Remaining gate: operator review/authorization of this implementation plan, including the reference-only collation migration and foundation/follow-on boundaries. Flexible reviewed Import/profiles and global missing-data settings remain separate accepted follow-ons. This document does not claim runtime implementation or authorize publication.
