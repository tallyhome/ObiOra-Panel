<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Updates\Livewire\SettingsIndex;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class UpdatePollStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_poll_closes_overlay_when_progress_reached_100(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));

        $history = UpdateHistory::query()->create([
            'from_version' => '2.9.0',
            'to_version' => '3.0.0',
            'status' => 'running',
            'progress' => 100,
            'progress_message' => 'Mise à jour terminée avec succès',
        ]);

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->set('pendingHistoryId', $history->id)
            ->set('updateRunning', true)
            ->call('pollUpdateStatus')
            ->assertSet('updateRunning', false)
            ->assertSet('pendingHistoryId', null);
    }
}
