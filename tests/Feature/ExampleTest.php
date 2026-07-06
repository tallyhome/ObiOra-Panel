<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_setup_without_admin(): void
    {
        $this->seed();

        $this->get('/')->assertRedirect('/setup');
    }
}
