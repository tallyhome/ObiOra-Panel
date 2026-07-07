<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServerStatus;
use App\Enums\ServerType;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
final class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'hostname' => fake()->domainName(),
            'ip_address' => fake()->ipv4(),
            'type' => ServerType::Vps,
            'status' => ServerStatus::Pending,
            'is_master' => false,
            'os_name' => 'Debian',
            'os_version' => '12',
            'agent_token' => bin2hex(random_bytes(32)),
            'metadata' => [],
        ];
    }

    public function master(): static
    {
        return $this->state(fn () => [
            'is_master' => true,
            'type' => ServerType::Local,
            'status' => ServerStatus::Online,
        ]);
    }
}
