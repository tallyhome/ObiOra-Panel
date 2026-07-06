<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_websites_index(): void
    {
        $this->seed();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $this->actingAs($user)->get('/websites')->assertOk();
    }

    public function test_website_belongs_to_server(): void
    {
        $this->seed();

        $server = Server::query()->where('is_master', true)->firstOrFail();

        $website = Website::query()->create([
            'server_id' => $server->id,
            'domain' => 'test.example.com',
            'document_root' => '/var/www/test.example.com/public',
            'php_version' => '8.3',
            'status' => WebsiteStatus::Active,
        ]);

        $this->assertTrue($website->server->is($server));
        $this->assertCount(1, $server->fresh()->websites);
    }
}
