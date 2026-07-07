<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Applications\ApplicationCatalog;
use App\Support\ApplicationIcon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ApplicationIconController extends Controller
{
    public function __invoke(string $slug, ApplicationCatalog $catalog, ApplicationIcon $icons): BinaryFileResponse|Response
    {
        $package = $catalog->find($slug);

        if ($package === null) {
            abort(404);
        }

        $filename = $icons->localIconFilename($package);

        if ($filename === null) {
            abort(404);
        }

        $path = $package->path.DIRECTORY_SEPARATOR.$filename;

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
