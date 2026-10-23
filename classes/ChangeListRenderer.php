<?php
namespace AdmHistory;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use DateTimeImmutable;

/**
 * Liste der Ein- und Austritte eines Jahres (overview.php?mode=changes): Nachname, Vorname
 * (verlinkt auf das Profil), Rollen im Jahr, Eintritt und Austritt. Art „all“ zeigt die
 * unterjährigen Bewegungen einer Kachelzeile (Stichtage 1. Januar und 31. Dezember), „joined“ und
 * „left“ alle Eintritte bzw. Austritte des Jahres (Stichtage 31. Dezember des Vorjahres und
 * 1. Januar des Folgejahres), siehe MemberCounter::changesInYear(). Art „switched“ (renderSwitches)
 * zeigt die Rollenwechsel, siehe MemberCounter::roleChangesInYear().
 *
 * Eintrag je Person: first_name, last_name, member_number, usr_uuid, roles (Rollennamen im Jahr), begin
 * (Eintrittsdatum Y-m-d oder null), end (Austrittsdatum Y-m-d oder null),
 * siehe MemberCounter::changesInYear().
 */
final class ChangeListRenderer
{
    public const KIND_ALL = 'all';
    public const KIND_JOINED = 'joined';
    public const KIND_LEFT = 'left';
    public const KIND_SWITCHED = 'switched';
    public const KINDS = [self::KIND_ALL, self::KIND_JOINED, self::KIND_LEFT, self::KIND_SWITCHED];

    /** Überschriften je Art */
    public const KIND_TITLES = [
        self::KIND_ALL      => 'Ein- und Austritte',
        self::KIND_JOINED   => 'Eintritte',
        self::KIND_LEFT     => 'Austritte',
        self::KIND_SWITCHED => 'Wechsel',
    ];

    /**
     * @param array<int, array{usr_uuid:string, first_name:string, last_name:string, member_number:string, roles:string[], begin:?string, end:?string}> $entries
     * @param string                                                                                                            $label     Beschriftung der Kachelzeile bzw. der Kachel
     * @param string[]                                                                                                          $roleNames Rollen, auf die sich die Liste bezieht
     * @param string                                                                                                            $kind      eine der KIND_*-Konstanten
     */
    public function render(PagePresenter $page, array $entries, string $label, array $roleNames, int $year, string $kind): void
    {
        $e = Html::escape(...);

        usort($entries, static fn(array $a, array $b): int =>
            [mb_strtolower($a['last_name']), mb_strtolower($a['first_name'])]
            <=> [mb_strtolower($b['last_name']), mb_strtolower($b['first_name'])]);

        $rolesText = count($roleNames) > 1 || $roleNames[0] !== $label
            ? '„' . $e($label) . '“ (' . $e(implode(', ', $roleNames)) . ')'
            : '„' . $e($label) . '“';
        $intro = match ($kind) {
            self::KIND_JOINED => 'Personen, die ' . $year . ' in ' . $rolesText . ' eingetreten sind, also am 31. Dezember '
                . ($year - 1) . ' in keiner dieser Rollen waren und im Jahr mindestens einen Tag in einer davon (auch ab dem 1. Januar).',
            self::KIND_LEFT => 'Personen, die ' . $year . ' aus ' . $rolesText . ' ausgetreten sind, also im Jahr mindestens einen Tag '
                . 'in einer dieser Rollen waren und am 1. Januar ' . ($year + 1) . ' in keiner mehr (auch zum 31. Dezember).',
            default => 'Personen, die ' . $year . ' mindestens einen Tag in ' . $rolesText . ' waren, aber nicht am 1. Januar '
                . 'und am 31. Dezember zugleich (unterjährige Ein- und Austritte).',
        };
        $emptyDateNote = $kind === self::KIND_ALL
            ? 'am 1. Januar bereits bzw. am 31. Dezember noch Mitglied.'
            : 'schon vor dem Jahr bzw. über das Jahr hinaus Mitglied.';

        $html = '<div class="card admidio-blog"><div class="card-body">'
            . '<p class="form-text">' . $intro . ' Ein leeres Datum bedeutet: ' . $emptyDateNote
            . ' Die Spalte „Rollen“ nennt die Rollen, in denen die Person im Jahr war.</p>';

        if ($entries === []) {
            $html .= '<p>Keine ' . self::KIND_TITLES[$kind] . ' ' . $year . '.</p></div></div>';
            $page->addHtml($html);

            return;
        }

        $html .= '<table class="table table-hover table-sm">'
            . '<thead><tr><th>Mitgliedsnummer</th><th>Nachname</th><th>Vorname</th><th>Rollen</th><th>Eintritt</th><th>Austritt</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $entry['usr_uuid']]);
            $html .= '<tr>'
                . '<td>' . $e($entry['member_number']) . '</td>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['last_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['first_name']) . '</a></td>'
                . '<td>' . $e(implode(', ', $entry['roles'])) . '</td>'
                . '<td>' . self::formatDate($entry['begin']) . '</td>'
                . '<td>' . self::formatDate($entry['end']) . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>'
            . '<p class="form-text mb-0">' . count($entries) . ' ' . (count($entries) === 1 ? 'Person' : 'Personen') . '</p>'
            . '</div></div>';

        $page->addHtml($html);
    }

    /**
     * Liste der Rollenwechsel (Art „switched“): Nachname, Vorname, Rollen vorher, Rollen nachher, Datum.
     *
     * @param array<int, array{usr_uuid:string, first_name:string, last_name:string, member_number:string, before:string[], after:string[], date:?string}> $entries
     * @param string[]                                                                                                           $roleNames Rollen der Kachel
     */
    public function renderSwitches(PagePresenter $page, array $entries, array $roleNames, int $year): void
    {
        $e = Html::escape(...);

        usort($entries, static fn(array $a, array $b): int =>
            [mb_strtolower($a['last_name']), mb_strtolower($a['first_name'])]
            <=> [mb_strtolower($b['last_name']), mb_strtolower($b['first_name'])]);

        $html = '<div class="card admidio-blog"><div class="card-body">'
            . '<p class="form-text">Personen, die am 31. Dezember ' . ($year - 1) . ' und am 1. Januar ' . ($year + 1)
            . ' in mindestens einer der Rollen ' . $e(implode(', ', $roleNames)) . ' waren, aber nicht in denselben. '
            . '„Vorher“ und „Nachher“ nennen die Rollen an diesen beiden Tagen, das Datum den Beginn der neuen Rolle '
            . '(ohne neue Rolle: das Ende der weggefallenen). Ein Hin- und Rückwechsel innerhalb des Jahres erscheint nicht.</p>';

        if ($entries === []) {
            $html .= '<p>Keine Wechsel ' . $year . '.</p></div></div>';
            $page->addHtml($html);

            return;
        }

        $html .= '<table class="table table-hover table-sm">'
            . '<thead><tr><th>Mitgliedsnummer</th><th>Nachname</th><th>Vorname</th><th>Vorher</th><th>Nachher</th><th>Datum</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $entry['usr_uuid']]);
            $html .= '<tr>'
                . '<td>' . $e($entry['member_number']) . '</td>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['last_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($entry['first_name']) . '</a></td>'
                . '<td>' . $e(implode(', ', $entry['before'])) . '</td>'
                . '<td>' . $e(implode(', ', $entry['after'])) . '</td>'
                . '<td>' . self::formatDate($entry['date']) . '</td>'
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
