<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Monitoring\MonitoringSlaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MonitoringSlaExportController extends Controller
{
    public function __invoke(Request $request, Server $server, MonitoringSlaService $sla): StreamedResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [30, 60, 90], true) ? $days : 30;

        $html = $sla->renderReportHtml($server, $days);
        $filename = 'sla-'.$server->id.'-'.$days.'d-'.now()->format('Y-m-d').'.html';

        return response()->streamDownload(
            fn () => print($html),
            $filename,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
