<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Models\MaintenanceWindow;
use App\Models\MonitoringIncident;
use App\Models\User;
use Illuminate\Support\Collection;

final class MaintenanceWindowService
{
    /**
     * @param  list<int>|null  $resourceIds
     */
    public function schedule(
        string $resourceType,
        ?array $resourceIds,
        \DateTimeInterface $startsAt,
        \DateTimeInterface $endsAt,
        ?string $note,
        ?User $creator,
    ): MaintenanceWindow {
        $window = MaintenanceWindow::query()->create([
            'resource_type' => $resourceType,
            'resource_ids' => $resourceIds !== null && $resourceIds !== [] ? array_values($resourceIds) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => $note,
            'created_by' => $creator?->id,
        ]);

        if ($window->isActive()) {
            $this->suppressOpenIncidents($window);
        }

        return $window;
    }

    public function cancel(MaintenanceWindow $window): void
    {
        $window->forceFill(['cancelled_at' => now()])->save();
    }

    public function isSilenced(string $resourceType, int $resourceId): bool
    {
        return $this->activeWindows()->contains(
            fn (MaintenanceWindow $window) => $this->windowCovers($window, $resourceType, $resourceId),
        );
    }

    public function isServerSilenced(int $serverId): bool
    {
        return $this->isSilenced('server', $serverId);
    }

    /** @return Collection<int, MaintenanceWindow> */
    public function activeWindows(): Collection
    {
        return MaintenanceWindow::query()->active()->orderBy('ends_at')->get();
    }

    /** @return Collection<int, MaintenanceWindow> */
    public function upcomingAndActive(int $limit = 50): Collection
    {
        return MaintenanceWindow::query()
            ->whereNull('cancelled_at')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    public function serverHasMaintenanceBadge(int $serverId): bool
    {
        return $this->isSilenced('server', $serverId);
    }

    private function windowCovers(MaintenanceWindow $window, string $resourceType, int $resourceId): bool
    {
        if ($window->resource_type === 'all') {
            return true;
        }

        if ($window->resource_type !== $resourceType) {
            return false;
        }

        $ids = $window->resource_ids ?? [];

        return $ids === [] || in_array($resourceId, $ids, true);
    }

    private function suppressOpenIncidents(MaintenanceWindow $window): void
    {
        $query = MonitoringIncident::query()->where('status', 'open');

        if ($window->resource_type !== 'all') {
            $ids = $window->resource_ids ?? [];
            $query->where('resource_type', $window->resource_type);

            if ($ids !== []) {
                $query->whereIn('resource_id', $ids);
            }
        }

        $query->get()->each(function (MonitoringIncident $incident): void {
            $meta = $incident->metadata ?? [];
            $meta['maintenance_suppressed'] = true;
            $incident->forceFill([
                'status' => 'resolved',
                'recovered_at' => now(),
                'metadata' => $meta,
            ])->save();
        });
    }
}
