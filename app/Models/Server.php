<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'type',
        'status',
        'is_master',
        'os_name',
        'os_version',
        'agent_token',
        'last_seen_at',
        'metadata',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServerType::class,
            'status' => ServerStatus::class,
            'is_master' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
            'tags' => 'array',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ServerNode::class);
    }

    public function primaryNode(): HasOne
    {
        return $this->hasOne(ServerNode::class)->where('is_primary', true);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function managedDatabases(): HasMany
    {
        return $this->hasMany(ManagedDatabase::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function installedApplications(): HasMany
    {
        return $this->hasMany(InstalledApplication::class);
    }

    public function diagnosticReports(): HasMany
    {
        return $this->hasMany(DiagnosticReport::class);
    }

    public function latestDiagnosticReport(): HasOne
    {
        return $this->hasOne(DiagnosticReport::class)->latestOfMany('generated_at');
    }

    public function crashAnalyzerMetrics(): HasMany
    {
        return $this->hasMany(CrashAnalyzerMetric::class);
    }

    public function crashAnalyzerEvents(): HasMany
    {
        return $this->hasMany(CrashAnalyzerEvent::class);
    }

    public function crashAnalyzerReports(): HasMany
    {
        return $this->hasMany(CrashAnalyzerReport::class);
    }

    public function metricSamples(): HasMany
    {
        return $this->hasMany(ServerMetricSample::class);
    }
}
