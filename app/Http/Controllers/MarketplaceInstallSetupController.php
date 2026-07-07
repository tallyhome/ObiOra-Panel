<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Applications\ApplicationCatalog;
use App\Services\Applications\ApplicationManager;
use App\Services\Core\ServerManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class MarketplaceInstallSetupController extends Controller
{
    public function store(
        Request $request,
        ApplicationCatalog $catalog,
        ApplicationManager $manager,
        ServerManager $serverManager,
    ): RedirectResponse {
        $slug = (string) $request->input('slug', '');
        $package = $catalog->find($slug);

        if ($package === null || ! $package->needsInstallWizard()) {
            return redirect()->route('plugins.index')->with('error', 'Application introuvable.');
        }

        $options = [];
        foreach ($package->installOptions() as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                $options[$name] = trim((string) $request->input($name, ''));
            }
        }

        try {
            $server = $serverManager->getCurrentServer();

            if ($server === null) {
                throw new InvalidArgumentException('Aucun serveur sélectionné.');
            }

            if ($package->hasInstallOptions()) {
                $options = $manager->validateInstallOptions($package, $options);
            }

            $manager->queueInstall($slug, $server, $options);

            return redirect()
                ->route('plugins.index')
                ->with('success', "Installation de {$package->name()} démarrée.");
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('plugins.index')
                ->withInput()
                ->with('install_setup_slug', $slug)
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Marketplace install setup failed', [
                'slug' => $slug,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('plugins.index')
                ->withInput()
                ->with('install_setup_slug', $slug)
                ->with('error', 'Erreur interne lors du lancement de l\'installation. Consultez les logs du panel.');
        }
    }
}
