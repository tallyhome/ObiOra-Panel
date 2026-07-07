<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ChangelogParser;
use Tests\TestCase;

final class ChangelogParserTest extends TestCase
{
    public function test_parses_version_sections(): void
    {
        $parser = new ChangelogParser;
        $sections = $parser->sections(3);

        $this->assertNotEmpty($sections);
        $this->assertSame('2.1.0', $sections[0]['version']);
        $this->assertNotEmpty($sections[0]['items']);
    }

    public function test_returns_notes_for_specific_version(): void
    {
        $parser = new ChangelogParser;
        $notes = $parser->notesForVersion('1.9.41');

        $this->assertNotNull($notes);
        $this->assertStringContainsString('Marketplace', $notes);
    }
}
