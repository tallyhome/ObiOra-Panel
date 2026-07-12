<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitorVisitService;
use Illuminate\Http\Response;

final class MonitorVisitController extends Controller
{
    public function pixel(string $token, MonitorVisitService $visits): Response
    {
        $visits->recordHit($token, request()->ip());

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function script(string $token, MonitorVisitService $visits): Response
    {
        $pixelUrl = route('monitoring.track.pixel', ['token' => $token]);

        $js = <<<JS
(function(){try{var i=new Image();i.src="{$pixelUrl}?t="+Date.now();}catch(e){}})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
