<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModuleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanelModule extends Model
{
    use HasFactory;

    protected $table = 'panel_modules';

    protected $fillable = [
        'slug',
        'name',
        'version',
        'status',
        'enabled_at',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'status' => ModuleStatus::class,
            'enabled_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(ModuleMetadata::class, 'module_slug', 'slug');
    }
}
