<?php

namespace App\Models;

use Database\Factories\GroupTagValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GroupTagValue extends Model
{
    /** @use HasFactory<GroupTagValueFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['group_tag_category_id', 'label', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['group_tag_category_id' => 'integer', 'sort_order' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GroupTagCategory::class, 'group_tag_category_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_tag_value')->withPivot('position');
    }
}
