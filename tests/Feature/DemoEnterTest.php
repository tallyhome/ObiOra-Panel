<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Demo\DemoAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class DemoEnterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['obiora.public_url' => 'http://panel.test']);
        URL::forceRootUrl('http://panel.test');
    }

    public function test_signed_demo_link_logs_in_client_and_redirects_dashboard(): void
    {
        $account = app(DemoAccountService::class)->create('demo@example.com', 'Demo', 1);
        $user = User::query()->findOrFail($account['user_id']);

        $this->get($account['login_url'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
