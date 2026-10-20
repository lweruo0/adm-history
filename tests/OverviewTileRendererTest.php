<?php
namespace AdmHistory\Tests;

use AdmHistory\OverviewTileRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Tests für das HTML der Übersichtskacheln: Tabelle je Jahr, Button nur bei Ein- oder Austritten.
 */
final class OverviewTileRendererTest extends TestCase
{
    public function testRendersTableWithButtonOnlyForRowsWithChanges(): void
    {
        $html = (new OverviewTileRenderer())->render([
            [
                'key'   => 'current',
                'year'  => 2026,
                'label' => 'aktuelles Jahr',
                'rows'  => [
                    ['label' => 'Aktiv',  'begin' => 10, 'end' => 12, 'changes' => 3, 'url' => 'https://example.org/overview.php?mode=changes&amp;year=2026&amp;row=0'],
                    ['label' => 'Passiv <x>', 'begin' => 5, 'end' => 5, 'changes' => 0, 'url' => ''],
                ],
                'joined'   => ['count' => 2, 'url' => 'https://example.org/overview.php?mode=changes&amp;year=2026&amp;kind=joined'],
                'left'     => ['count' => 0, 'url' => ''],
                'switched' => ['count' => 1, 'url' => 'https://example.org/overview.php?mode=changes&amp;year=2026&amp;kind=switched'],
            ],
        ], 'adm-history', 'https://example.org/index.php');

        self::assertStringContainsString('id="plugin-adm-history-current"', $html);
        self::assertStringContainsString('Mitglieder 2026', $html);
        self::assertStringContainsString('<td>Passiv &lt;x&gt;</td>', $html);
        self::assertStringContainsString('href="https://example.org/overview.php?mode=changes&amp;amp;year=2026&amp;amp;row=0" target="_blank"', $html);
        self::assertStringContainsString('bi-people"></i> 3</a>', $html);
        self::assertStringContainsString('3 Personen mit Ein- oder Austritt 2026', $html);
        // Zeilen-Button, „Eintritte“ und „Wechsel“ sind Links, „Austritte“ ist ohne Personen inaktiv
        self::assertSame(3, substr_count($html, '<a class="btn btn-sm'));
        self::assertStringContainsString('kind=joined" target="_blank"', $html);
        self::assertStringContainsString('Eintritte (2)</a>', $html);
        self::assertStringContainsString('bi-arrow-left-right"></i> Wechsel (1)</a>', $html);
        self::assertStringContainsString('<span class="btn btn-sm btn-secondary disabled" aria-disabled="true" title="Keine Austritte 2026">', $html);
        self::assertStringContainsString('Austritte (0)</span>', $html);
    }

    public function testRendersNoticeWithoutRows(): void
    {
        $html = (new OverviewTileRenderer())->render(
            [['key' => 'next', 'year' => 2027, 'label' => 'Folgejahr', 'rows' => [], 'joined' => ['count' => 0, 'url' => ''], 'left' => ['count' => 0, 'url' => ''], 'switched' => ['count' => 0, 'url' => '']]],
            'adm-history',
            'https://example.org/index.php'
        );

        self::assertStringContainsString('Keine Rollen konfiguriert', $html);
        self::assertStringNotContainsString('<table', $html);
        self::assertStringNotContainsString('Eintritte', $html);
    }
}
