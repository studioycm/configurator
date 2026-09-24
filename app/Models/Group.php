<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['legacy_id', 'parent_id', 'name', 'description', 'sort_order', 'configurator_id', 'result_settings'];

    /** @var array<string, mixed> */
    protected $attributes = ['sort_order' => 0, 'result_settings' => '{"default_page_size":10,"allow_page_size_change":false,"page_size_options":[1,2,10]}'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['parent_id' => 'integer', 'configurator_id' => 'integer', 'sort_order' => 'integer', 'result_settings' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function filters(): HasMany
    {
        return $this->hasMany(GroupFilter::class);
    }

    public function subGroups(): HasMany
    {
        return $this->hasMany(SubGroup::class);
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    /** @return Collection<int, self> */
    public function ancestorTrail(): Collection
    {
        $groups = self::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $trail = collect();
        $parentId = $this->parent_id;
        while ($parentId !== null && ! $trail->has($parentId) && ($parent = $groups->get($parentId))) {
            $trail->put($parentId, $parent);
            $parentId = $parent->parent_id;
        }

        return $trail->reverse()->values();
    }
}
