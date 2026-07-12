<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MonitorType;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
final class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->domainWord().' monitor',
            'type' => MonitorType::Https,
            'target' => 'https://example.com',
            'interval_seconds' => 300,
            'is_active' => true,
        ];
    }

    public function port(int $port = 443): static
    {
        return $this->state(fn () => [
            'type' => MonitorType::Port,
            'target' => 'example.com',
            'port' => $port,
        ]);
    }
}
