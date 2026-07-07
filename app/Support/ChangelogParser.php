<?php

declare(strict_types=1);

namespace App\Support;

final class ChangelogParser
{
    public function path(): string
    {
        return base_path('CHANGELOG.md');
    }

    /**
     * @return list<array{version: string, date: ?string, body: string, items: list<string>}>
     */
    public function sections(int $limit = 8): array
    {
        $content = $this->readContent();
        if ($content === '') {
            return [];
        }

        $sections = [];
        $current = null;

        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match('/^##\s+v?(\d+\.\d+\.\d+)\s*(?:-\s*(.+))?$/i', $line, $matches)) {
                if ($current !== null) {
                    $sections[] = $this->finalizeSection($current);
                }

                $current = [
                    'version' => $matches[1],
                    'date' => isset($matches[2]) ? trim($matches[2]) : null,
                    'lines' => [],
                ];

                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $sections[] = $this->finalizeSection($current);
        }

        return array_slice($sections, 0, max(1, $limit));
    }

    public function notesForVersion(string $version): ?string
    {
        $normalized = ltrim(trim($version), 'vV');

        foreach ($this->sections(50) as $section) {
            if ($section['version'] === $normalized) {
                return $section['body'] !== '' ? $section['body'] : implode("\n", $section['items']);
            }
        }

        return null;
    }

    private function readContent(): string
    {
        $path = $this->path();

        if (! is_readable($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    /**
     * @param  array{version: string, date: ?string, lines: list<string>}  $section
     * @return array{version: string, date: ?string, body: string, items: list<string>}
     */
    private function finalizeSection(array $section): array
    {
        $items = [];
        $bodyLines = [];

        foreach ($section['lines'] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                $items[] = trim($matches[1]);
            }

            $bodyLines[] = $line;
        }

        return [
            'version' => $section['version'],
            'date' => $section['date'],
            'body' => trim(implode("\n", $bodyLines)),
            'items' => $items,
        ];
    }
}
