<?php

declare(strict_types=1);

namespace App\Events\CrashAnalyzer;

use App\Models\CrashAnalyzerEvent;
use App\Models\Server;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnexpectedRebootDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Server $server,
        public readonly CrashAnalyzerEvent $event,
    ) {}
}
