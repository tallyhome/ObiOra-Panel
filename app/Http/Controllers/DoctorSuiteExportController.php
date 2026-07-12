<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\Diagnostics\DoctorSuiteExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DoctorSuiteExportController extends Controller
{
    public function json(Server $server, Request $request, DoctorSuiteExportService $export): StreamedResponse
    {
        $since = $export->resolveSince($request->integer('hours') ?: null);

        return $export->exportJson($server, $since);
    }

    public function csv(Server $server, Request $request, DoctorSuiteExportService $export): StreamedResponse
    {
        $since = $export->resolveSince($request->integer('hours') ?: null);

        return $export->exportCsv($server, $since);
    }

    public function html(Server $server, Request $request, DoctorSuiteExportService $export): \Illuminate\Http\Response
    {
        $since = $export->resolveSince($request->integer('hours') ?: null);

        if ($request->boolean('inline')) {
            return $export->viewHtml($server, $since);
        }

        return $export->exportHtml($server, $since);
    }
}
