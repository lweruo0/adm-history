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
            ],
        ], 'adm-history', 'https://example.org/index.php');

        self::assertStringContainsString('id="plugin-adm-history-current"', $html);
        self::assertStringContainsString('Mitglieder 2026', $html);
        self::assertStringContainsString('<td>Passiv &lt;x&gt;</td>', $html);
        self::assertStringContainsString('href="https://example.org/overview.php?mode=changes&amp;amp;year=2026&amp;amp;row=0" target="_blank"', $html);
        self::assertSame(1, substr_count($html, 'class="btn btn-sm'));
        self::assertStringContainsString('bi-people"></i> 3</a>', $html);
        self::assertStringContainsString('3 Personen mit Ein- oder Austritt 2026', $html);
    }

    public function testRendersNoticeWithoutRows(): void
    {
        $html = (new OverviewTileRenderer())->render(
            [['key' => 'next', 'year' => 2027, 'label' => 'Folgejahr', 'rows' => []]],
            'adm-history',
            'https://example.org/index.php'
        );

        self::assertStringContainsString('Keine Rollen konfiguriert', $html);
        self::assertStringNotContainsString('<table', $html);
    }
}
