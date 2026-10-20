<?php
namespace AdmHistory;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use DateTimeImmutable;

/**
 * Liste der unterjährigen Ein- und Austritte einer Kachelzeile (overview.php?mode=changes):
 * Nachname, Vorname (verlinkt auf das Profil), Eintritt und Austritt im Jahr.
 *
 * Eintrag je Person: first_name, last_name, usr_uuid, begin (Eintrittsdatum Y-m-d oder null),
 * end (Austrittsdatum Y-m-d oder null), siehe MemberCounter::changesInYear().
 */
final class ChangeListRenderer
{
    /**
     * @param array<int, array{usr_uuid:string, first_name:string, last_name:string, begin:?string, end:?string}> $entries
     * @param string                                                                                            $label   Beschriftung der Kachelzeile
     * @param string[]                                                                                          $roleNames Rollen der Zeile
     */
    public function render(PagePresenter $page, array $entries, string $label, array $roleNames, int $year): void
    {
        $e = Html::escape(...);

        usort($entries, static fn(array $a, array $b): int =>
            [mb_strtolower($a['last_name']), mb_strtolower($a['first_name'])]
            <=> [mb_strtolower($b['last_name']), mb_strtolower($b['first_name'])]);

        $html = '<div class="card admidio-blog"><div class="card-body">'
            . '<p class="form-text">Personen, die ' . $year . ' mindestens einen Tag in der Rolle „' . $e($label) . '“'
            . (count($roleNames) > 1 || $roleNames[0] !== $label ? ' (' . $e(implode(', ', $roleNames)) . ')' : '')
            . ' waren, aber nicht am 1. Januar und am 31. Dezember zugleich. '
            . 'Ein leeres Feld bedeutet: am 1. Januar bereits bzw. am 31. Dezember noch Mitglied.</p>';

        if ($entries === []) {
            $html .= '<p>Keine unterjährigen Ein- oder Austritte.</p></div></div>';
            $page->addHtml($html);

            return;
        }

        $html .= '<table class="table table-hover table-sm">'
            . '<thead><tr><th>Nachname</th><th>Vorname</th><th>Eintritt</th><th>Austritt</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $entry['usr_uuid']]);
            $html .= '<tr>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['last_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['first_name']) . '</a></td>'
                . '<td>' . self::formatDate($entry['begin']) . '</td>'
                . '<td>' . self::formatDate($entry['end']) . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>'
            . '<p class="form-text mb-0">' . count($entries) . ' ' . (count($entries) === 1 ? 'Person' : 'Personen') . '</p>'
            . '</div></div>';

        $page->addHtml($html);
    }

    /** Datum Y-m-d als TT.MM.JJJJ; null ergibt einen leeren Text. */
    private static function formatDate(?string $date): string
    {
        if ($date === null) {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed === false ? Html::escape($date) : $parsed->format('d.m.Y');
    }
}
