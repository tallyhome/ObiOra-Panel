<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Demo\DemoAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DemoAccountController extends Controller
{
    public function __construct(private DemoAccountService $service) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'ttl_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $ttl = $validated['ttl_hours'] ?? (int) config('obiora.site_api.demo_ttl_hours', 24);
        $name = $validated['name'] ?? explode('@', $validated['email'])[0];

        $account = $this->service->create($validated['email'], $name, $ttl);

        return response()->json($account, 201);
    }

    public function destroy(int $userId): JsonResponse
    {
        if (! $this->service->delete($userId)) {
            return response()->json(['error' => 'Demo account not found'], 404);
        }

        return response()->json(['deleted' => true]);
    }
}
