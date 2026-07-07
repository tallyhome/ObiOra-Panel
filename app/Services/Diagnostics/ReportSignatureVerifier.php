<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Server;

final class ReportSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, Server $server): bool
    {
        $signatureBlock = $payload['signature'] ?? null;
        if (! is_array($signatureBlock)) {
            return false;
        }

        $expected = (string) ($signatureBlock['value'] ?? '');
        if ($expected === '') {
            return false;
        }

        $key = $this->resolveSigningKey($server);
        if ($key === '') {
            return false;
        }

        $unsigned = $payload;
        unset($unsigned['signature']);

        $json = json_encode($unsigned, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        // Python uses sort_keys=True — replicate stable key ordering.
        $sorted = $this->sortKeysRecursive($unsigned);
        $sortedJson = json_encode($sorted, JSON_UNESCAPED_UNICODE);
        if ($sortedJson === false) {
            return false;
        }

        $computed = hash_hmac('sha256', $sortedJson, $key);

        return hash_equals($expected, $computed);
    }

    private function resolveSigningKey(Server $server): string
    {
        $metadataKey = $server->metadata['doctor_signing_key'] ?? null;
        if (is_string($metadataKey) && $metadataKey !== '') {
            return $this->normalizeKey($metadataKey);
        }

        $envKey = (string) config('obiora.diagnostics.signing_key', '');
        if ($envKey !== '') {
            return $this->normalizeKey($envKey);
        }

        return '';
    }

    private function normalizeKey(string $key): string
    {
        if (ctype_xdigit($key) && strlen($key) === 64) {
            $binary = hex2bin($key);

            return $binary !== false ? $binary : $key;
        }

        return $key;
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    private function sortKeysRecursive(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            return array_map(fn (mixed $item) => $this->sortKeysRecursive($item), $data);
        }

        ksort($data);
        foreach ($data as $key => $value) {
            $data[$key] = $this->sortKeysRecursive($value);
        }

        return $data;
    }
}
