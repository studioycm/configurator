<?php

use App\CatalogWriteFailure;
use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\RuleEffect;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorRuleExchange;
use App\Services\ConfiguratorRuleImport;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    DB::commit();
    $this->importTransactions = app('db.transactions');
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    DB::connection()->setTransactionManager($transactions);
    $this->importContextIds = CatalogContextSettings::pluck('id')->all();
    $this->importActor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->importOwner = Configurator::factory()->create();
    $this->importAttribute = Attribute::factory()->create();
    $this->importInclusion = ConfiguratorAttribute::factory()->for($this->importOwner)->for($this->importAttribute)->create();
    $this->importValue = Value::factory()->create();
    $this->importOption = Option::factory()->for($this->importAttribute)->for($this->importValue)->create();
    $this->importIncludedOption = ConfiguratorOption::factory()->for($this->importInclusion, 'configuratorAttribute')->for($this->importOption)->create();
    $this->importInclusion->update(['default_configurator_option_id' => $this->importIncludedOption->id]);
    $this->importRule = ConfiguratorRule::factory()->for($this->importOwner)->create(['label' => 'Before import']);
    RuleEffect::factory()->for($this->importRule, 'rule')->create(['target_configurator_attribute_id' => $this->importInclusion->id, 'kind' => 'SetLabel', 'target_scope' => 'Attribute', 'display_value' => 'Exact 001']);
});

afterEach(function () {
    $this->importRule->effects()->delete();
    $this->importRule->delete();
    $this->importInclusion->update(['default_configurator_option_id' => null]);
    $this->importIncludedOption->delete();
    $this->importInclusion->delete();
    $this->importOwner->delete();
    $this->importOption->delete();
    $this->importValue->delete();
    $this->importAttribute->delete();
    $this->importActor->delete();
    CatalogContextSettings::whereNotIn('id', $this->importContextIds)->delete();
    app()->instance('db.transactions', $this->importTransactions);
    DB::connection()->setTransactionManager($this->importTransactions);
    DB::beginTransaction();
});

test('a Rules import failure after actual database commit is unknown and cannot replay its spent approval', function () {
    $service = app(ConfiguratorRuleImport::class);
    $rows = app(ConfiguratorRuleExchange::class)->snapshot($this->importRule)['rows'];
    $rows[0] = array_replace($rows[0], ['import' => '1', 'rule_label' => 'Committed import']);
    $preview = $service->previewRows($this->importActor, $rows, ['allow_update' => true], $this->importOwner->id);
    expect($preview['errors'])->toBe([]);
    $pending = true;
    $rule = $this->importRule;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pending, $rule): void {
        if ($pending && $event->connection->transactionLevel() === 0 && $rule->fresh()->label === 'Committed import') {
            throw new RuntimeException('Injected import committed callback failure');
        }
    });
    try {
        try {
            $service->apply($this->importActor, $preview['token'], $this->importOwner->id);
            $this->fail('The lost commit exit must not be reported as an acknowledged save.');
        } catch (CatalogWriteFailure $exception) {
            expect($exception->rolledBack)->toBeFalse();
        }
    } finally {
        $pending = false;
    }
    expect(DB::transactionLevel())->toBe(0);
    expect($this->importRule->fresh()->label)->toBe('Committed import');
    expect(fn () => $service->apply($this->importActor, $preview['token'], $this->importOwner->id))->toThrow(ValidationException::class);
    $saved = $service->inspectCurrent($this->importActor, [$this->importOwner->id], $this->importOwner->id);
    expect($saved[0]['rules'][0]['label'])->toBe('Committed import');
    expect($saved[0]['rules'][0]['summary'])->toContain('Exact 001');
});
