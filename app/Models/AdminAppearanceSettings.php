<?php

namespace App\Models;

use Database\Factories\AdminAppearanceSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAppearanceSettings extends Model
{
    /** @use HasFactory<AdminAppearanceSettingsFactory> */
    use HasFactory;

    protected $table = 'admin_appearance_settings';

    protected $fillable = ['settings', 'version'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'version' => 'integer'];
    }
}
