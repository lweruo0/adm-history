<?php
namespace AdmHistory;

/**
 * HTML der Kacheln für die Admidio-Übersichtsseite (overview.php): je Jahr eine kleine Tabelle mit
 * den Spalten Rolle, 01.01., 31.12. und einem Button, der bei unterjährigen Ein- oder Austritten die
 * Liste dieser Personen in einer neuen Registerkarte öffnet.
 *
 * Die Ausgabe entsteht ohne Smarty-Vorlage direkt in PHP, damit Änderungen am Plugin sofort wirken
 * und kein übersetztes Template zwischengespeichert wird. Die Klasse kennt kein Admidio.
 *
 * Unter der Tabelle stehen die Buttons „Eintritte“, „Austritte“ und „Wechsel“ für alle Rollen der
 * Kachel zusammen.
 *
 * Kachel je Eintrag: key (previous/current/next), year, label (z. B. „Folgejahr“), rows mit
 * label, begin, end, changes (siehe MemberCounter::yearTable()) und url (Link zur Liste oder ''),
 * joined, left und switched jeweils mit count und url (Liste der Eintritte, Austritte, Wechsel).
 */
final class OverviewTileRenderer
{
    /**
     * @param array<int, array{key:string, year:int, label:string, rows:array<int, array{label:string, begin:int, end:int, changes:int, url:string}>, joined:array{count:int, url:string}, left:array{count:int, url:string}, switched:array{count:int, url:string}}> $tiles
     * @param string $pluginName Ordnername des Plugins (für die HTML-IDs)
     * @param string $historyUrl URL der Mitglieder-Historie (Ziel der Überschrift)
     */
    public function render(array $tiles, string $pluginName, string $historyUrl): string
    {
        $e = Html::escape(...);

        $html = '';
        foreach ($tiles as $tile) {
            $html .= '<div id="plugin-' . $e($pluginName) . '-' . $e($tile['key']) . '" class="admidio-plugin-content">'
                . '<h3><a href="' . $e($historyUrl) . '" title="Mitglieder-Historie öffnen">Mitglieder ' . $tile['year'] . '</a></h3>'
                . '<p class="text-muted small mb-2">' . $e($tile['label']) . ': Personen in der Rolle am Stichtag</p>';

            if ($tile['rows'] === []) {
                $html .= '<p>Keine Rollen konfiguriert („overviewRoles“ in mitgliedsarten.php).</p></div>';
                continue;
            }

            $html .= '<table class="table table-sm mb-2"><thead><tr>'
                . '<th>Rolle</th><th class="text-end">01.01.</th><th class="text-end">31.12.</th>'
                . '<th class="text-end"><span class="visually-hidden">Ein- und Austritte</span></th>'
                . '</tr></thead><tbody>';
            foreach ($tile['rows'] as $row) {
                $html .= '<tr>'
                    . '<td>' . $e($row['label']) . '</td>'
                    . '<td class="text-end">' . $row['begin'] . '</td>'
                    . '<td class="text-end">' . $row['end'] . '</td>'
                    . '<td class="text-end py-0 align-middle">' . $this->changesButton($row, $tile['year']) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>'
                . '<div class="d-flex gap-2 mb-2">'
                . $this->kindButton('Eintritte', 'bi-box-arrow-in-right', $tile['joined'], $tile['year'])
                . $this->kindButton('Austritte', 'bi-box-arrow-right', $tile['left'], $tile['year'])
                . $this->kindButton('Wechsel', 'bi-arrow-left-right', $tile['switched'], $tile['year'])
                . '</div></div>';
        }

        return $html;
    }

    /**
     * Button „Eintritte“ bzw. „Austritte“ mit Anzahl; ohne Personen inaktiv.
     * @param array{count:int, url:string} $kind
     */
    private function kindButton(string $label, string $icon, array $kind, int $year): string
    {
        $text = '<i class="bi ' . $icon . '"></i> ' . $label . ' (' . $kind['count'] . ')';
        if ($kind['count'] <= 0 || $kind['url'] === '') {
            return '<span class="btn btn-sm btn-secondary disabled" aria-disabled="true" title="Keine ' . $label . ' ' . $year . '">' . $text . '</span>';
        }

        return '<a class="btn btn-sm btn-secondary" href="' . Html::escape($kind['url']) . '" target="_blank" rel="noopener" title="'
            . $label . ' ' . $year . ' in neuer Registerkarte anzeigen">' . $text . '</a>';
    }

    /**
     * Kleiner Button zur Liste der Ein- und Austritte; leer, wenn es im Jahr keine gab.
     * @param array{label:string, changes:int, url:string} $row
     */
    private function changesButton(array $row, int $year): string
    {
        if ($row['changes'] <= 0 || $row['url'] === '') {
            return '';
        }
        $title = $row['changes'] . ($row['changes'] === 1 ? ' Person' : ' Personen') . ' mit Ein- oder Austritt '
            . $year . ' in „' . $row['label'] . '“ in neuer Registerkarte anzeigen';

        return '<a class="btn btn-sm btn-secondary py-0 px-1" href="' . Html::escape($row['url']) . '" target="_blank" rel="noopener" title="'
            . Html::escape($title) . '"><i class="bi bi-people"></i> ' . $row['changes'] . '</a>';
    }
}
