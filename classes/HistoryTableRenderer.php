<?php
namespace AdmHistory;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;

/**
 * Stellt die Mitglieder-Historie als reine Ansicht dar: Vorname, Nachname und eine Spalte je Jahr
 * (absteigend) mit den Mitgliedsarten des Jahres als farbige Kürzel. Geändert werden Mitgliedsart
 * und Zusatzbeiträge im Schnellwechsel (quick_change.php), nicht hier.
 *
 * Die Tabelle wird mit der von Admidio mitgelieferten DataTables-Bibliothek durchsuchbar und nach
 * Vor- und Nachname sortierbar gemacht (die Jahresspalten sind nicht sortierbar, die Zeilen sind
 * kompakt).
 */
final class HistoryTableRenderer
{
    private const TABLE_ID = 'adm_history_table';

    /** Auswahlmöglichkeiten für „Jahre zurück“; 0 = alle */
    private const YEAR_OPTIONS = [5, 10, 15, 20, 30, 50, 0];

    /** Filter nach aktueller Mitgliedschaft (GET-Parameter status) */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FORMER = 'former';
    public const STATUS_ALL = 'all';
    public const STATUS_OPTIONS = [self::STATUS_ACTIVE, self::STATUS_FORMER, self::STATUS_ALL];

    /** Spalte „Ununterbrochen seit“ (GET-Parameter duration): alle Mitgliedsarten oder ein Kürzel */
    public const DURATION_ALL = 'all';

    /** Anzeigetexte des Filters in Anzeigereihenfolge */
    private const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Aktive Kontakte',
        self::STATUS_FORMER => 'Ehemalige Kontakte',
        self::STATUS_ALL    => 'Alle Kontakte',
    ];

    public function __construct(
        private readonly AdmidioContext $context,
        private readonly MembershipTypeConfig $config,
        private readonly YearHistory $history,
    ) {
    }

    /**
     * Filter nach aktueller Mitgliedschaft (GET-Parameter status), Auswahl „Jahre zurück“
     * (GET-Parameter years), Auswahl für die Spalte „Ununterbrochen seit“ (GET-Parameter duration)
     * und Legende der Mitgliedsarten.
     *
     * @param int    $selectedYears    aktuell gewählte Anzahl Jahre vor dem aktuellen Jahr (0 = alle)
     * @param string $selectedStatus   aktuell gewählter Filter (eine der STATUS_*-Konstanten)
     * @param string $selectedDuration DURATION_ALL oder Kürzel einer Mitgliedsart
     */
    public function renderToolbar(PagePresenter $page, string $pluginUrl, int $selectedYears, string $selectedStatus, string $selectedDuration): void
    {
        $e = Html::escape(...);

        $options = self::YEAR_OPTIONS;
        if (!in_array($selectedYears, $options, true)) {
            $options[] = $selectedYears;
        }
        sort($options);
        // „alle“ (0) ans Ende
        $options = array_values(array_filter($options, static fn(int $years): bool => $years !== 0));
        $options[] = 0;

        $html = '<div class="card admidio-blog mb-4"><div class="card-body">'
            . '<form method="get" action="' . $e($pluginUrl) . '" id="adm_history_filter_form" class="row g-2 align-items-end">'
            . '<div class="col-12 col-sm-auto">'
            . '<label for="adm_history_status" class="form-label fw-bold">Kontakte</label>'
            . '<select class="form-select adm-history-filter" id="adm_history_status" name="status">';
        foreach (self::STATUS_LABELS as $status => $label) {
            $html .= '<option value="' . $status . '"' . ($status === $selectedStatus ? ' selected' : '') . '>' . $e($label) . '</option>';
        }
        $html .= '</select></div>'
            . '<div class="col-12 col-sm-auto">'
            . '<label for="adm_history_years" class="form-label fw-bold">Jahre zurück</label>'
            . '<select class="form-select adm-history-filter" id="adm_history_years" name="years">';
        foreach ($options as $years) {
            $html .= '<option value="' . $years . '"' . ($years === $selectedYears ? ' selected' : '') . '>'
                . ($years === 0 ? 'alle Jahre' : $years . ' Jahre') . '</option>';
        }
        $html .= '</select></div>'
            . '<div class="col-12 col-sm-auto">'
            . '<label for="adm_history_duration" class="form-label fw-bold">Ununterbrochen seit</label>'
            . '<select class="form-select adm-history-filter" id="adm_history_duration" name="duration">'
            . '<option value="' . self::DURATION_ALL . '"' . ($selectedDuration === self::DURATION_ALL ? ' selected' : '') . '>Mitglied (alle Mitgliedsarten)</option>';
        foreach ($this->config->getTypes() as $type) {
            $html .= '<option value="' . $e($type->key) . '"' . ($type->key === $selectedDuration ? ' selected' : '') . '>' . $e($type->name) . '</option>';
        }
        $html .= '</select></div>'
            . '<div class="col-12 col-sm"><div class="fw-bold form-label">Mitgliedsarten</div><div>';
        foreach ($this->config->getTypes() as $type) {
            $html .= $this->badge($type) . ' <span class="me-3">' . $e($type->name)
                . ' <span class="text-muted small">(' . $e(implode(', ', $type->roleNames)) . ')</span></span>';
        }
        if ($this->config->getCommonRoles() !== []) {
            $html .= $this->unknownBadge() . ' <span class="me-3">' . $e($this->unknownTitle()) . '</span>';
        }
        $html .= '</div></div>'
            . '<div class="col-12 form-text">Je Jahr stehen alle Mitgliedsarten, in denen die Person in diesem Jahr mindestens einen Tag war. '
            . 'Geändert wird die Mitgliedsart im Schnellwechsel. „Ununterbrochen seit“ zeigt den Beginn der heute laufenden '
            . 'Mitgliedschaft ohne Unterbrechung (aneinander anschließende Zeiträume zählen als eine) und die vollen Jahre; '
            . 'ein Klick auf die Spaltenüberschrift sortiert nach der Dauer. „Aktive Kontakte“ sind heute in einer der konfigurierten Rollen, „Ehemalige Kontakte“ waren es früher. '
            . 'Personen ohne Mitgliedsart im angezeigten Zeitraum werden ausgeblendet.</div>'
            . '</form></div></div>';

        $page->addHtml($html);
        $page->addJavascript('
            document.querySelectorAll("#adm_history_filter_form .adm-history-filter").forEach(function (select) {
                select.addEventListener("change", function () {
                    document.getElementById("adm_history_filter_form").submit();
                });
            });', true);
    }

    /**
     * Tabelle mit allen Personen.
     *
     * @param array<string, array<string, mixed>> $users    Personen aus HistoryLoader::loadAll()
     * @param int[]                               $years    anzuzeigende Jahre, absteigend
     * @param string                              $duration Spalte „Ununterbrochen seit“: DURATION_ALL oder Kürzel einer Mitgliedsart
     */
    public function render(PagePresenter $page, array $users, array $years, string $duration = self::DURATION_ALL): void
    {
        $e = Html::escape(...);
        $today = date('Y-m-d');

        $rows = [];
        foreach ($users as $user) {
            $cells = [];
            $visible = false;
            foreach ($years as $year) {
                $state = $this->history->yearState($user['periods'], $year, $user['commonPeriods']);
                $inYear = $this->history->typesInYear($user['periods'], $year);
                $visible = $visible || $inYear !== [] || $state['unknown'];
                $cells[] = $this->historyCell($inYear, $state['unknown']);
            }
            if (!$visible) {
                continue;
            }

            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $user['usr_uuid']]);
            $rows[] = '<tr data-user="' . $e($user['usr_uuid']) . '">'
                . '<td><a href="' . $profileUrl . '">' . $e($user['first_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($user['last_name']) . '</a></td>'
                . $this->durationCell($this->durationPeriods($user, $duration), $today)
                . implode('', $cells) . '</tr>';
        }

        $durationTitle = $duration === self::DURATION_ALL ? 'Mitglied' : ($this->config->getType($duration)->name ?? $duration);
        $headers = '<th>Vorname</th><th>Nachname</th>'
            . '<th title="' . $e($durationTitle) . ' ohne Unterbrechung bis heute">' . $e($durationTitle) . ' seit</th>';
        foreach ($years as $year) {
            $headers .= '<th class="text-center adm-history-year">' . $year . '</th>';
        }

        $page->addHtml('<style>
            .adm-history-badge { display: inline-block; min-width: 1.7em; padding: 0 .3em; margin: 0 1px; border-radius: .25rem;
                line-height: 1.3; text-align: center; font-weight: 600; color: #212529; border: 1px solid rgba(0,0,0,.15); }
            .adm-history-unknown { background-color: #e9ecef; color: #6c757d; }
            #' . self::TABLE_ID . ' td, #' . self::TABLE_ID . ' th { white-space: nowrap; padding: .1rem .4rem; line-height: 1.3; vertical-align: middle; }
            #' . self::TABLE_ID . ' td.adm-history-year { text-align: center; }
            /* Jahresspalten sind nicht sortierbar: kein Sortier-Symbol und normaler Mauszeiger */
            #' . self::TABLE_ID . ' th.adm-history-year { cursor: default; }
            #' . self::TABLE_ID . ' th.adm-history-year::before, #' . self::TABLE_ID . ' th.adm-history-year::after { display: none; }
        </style>');

        if ($rows === []) {
            $page->addHtml('<div class="alert alert-info" role="alert">Im gewählten Zeitraum gibt es keine Personen mit einer Mitgliedsart.</div>');
            return;
        }

        $page->addHtml('<div class="table-responsive"><table id="' . self::TABLE_ID . '" class="table table-sm table-hover w-100">'
            . '<thead><tr>' . $headers . '</tr></thead><tbody>' . implode('', $rows) . '</tbody></table></div>');

        $this->addJavascript($page);
    }

    /**
     * Zelle eines vergangenen Jahres: farbige Kürzel aller Mitgliedsarten des Jahres; „?“, wenn die
     * Person Mitglied ohne ermittelbare Mitgliedsart war.
     *
     * @param string[] $typeKeys
     */
    private function historyCell(array $typeKeys, bool $unknown): string
    {
        $badges = '';
        foreach ($typeKeys as $typeKey) {
            $type = $this->config->getType($typeKey);
            if ($type !== null) {
                $badges .= $this->badge($type);
            }
        }
        if ($unknown) {
            $badges .= $this->unknownBadge();
        }

        return '<td class="adm-history-year">' . $badges . '</td>';
    }

    /**
     * Zeiträume für die Spalte „Ununterbrochen seit“: alle Mitgliedsarten und die gemeinsamen
     * Rollen oder nur die gewählte Mitgliedsart.
     *
     * @param array<string, mixed> $user
     * @return array<int, array{begin:string, end:string}>
     */
    private function durationPeriods(array $user, string $duration): array
    {
        if ($duration === self::DURATION_ALL) {
            return array_merge($user['periods'], $user['commonPeriods']);
        }

        return array_values(array_filter($user['periods'], static fn(array $period): bool => $period['type'] === $duration));
    }

    /**
     * Zelle „Ununterbrochen seit“: Beginn und volle Jahre; sortiert wird nach dem Beginn (data-order),
     * ohne laufende Mitgliedschaft ans Ende.
     *
     * @param array<int, array{begin:string, end:string}> $periods
     */
    private function durationCell(array $periods, string $today): string
    {
        $since = $this->history->continuousSince($periods, $today);
        if ($since === null) {
            return '<td data-order="9999-12-31"></td>';
        }
        $years = (new \DateTimeImmutable($since))->diff(new \DateTimeImmutable($today))->y;

        return '<td data-order="' . Html::escape($since) . '">' . date('d.m.Y', (int) strtotime($since))
            . ' <span class="text-muted">(' . $years . ' J.)</span></td>';
    }

    /**
     * Kennzeichen „?“: Mitglied in einer gemeinsamen Rolle, aber ohne ermittelbare Mitgliedsart.
     */
    private function unknownBadge(): string
    {
        return '<span class="adm-history-badge adm-history-unknown" title="'
            . Html::escape($this->unknownTitle()) . '">?</span>';
    }

    /** Erklärung des Kennzeichens „?“ (Tooltip und Legende). */
    private function unknownTitle(): string
    {
        $roles = $this->config->getCommonRoles();
        $roleText = $roles === [] ? 'einer gemeinsamen Rolle' : 'der Rolle „' . implode('“, „', $roles) . '“';

        return 'Mitglied in ' . $roleText . ', aber Mitgliedsart nicht ermittelbar';
    }

    /** Farbiges Kürzel einer Mitgliedsart. */
    private function badge(MembershipType $type): string
    {
        return '<span class="adm-history-badge" style="background-color: ' . Html::escape($type->color) . '" title="'
            . Html::escape($type->name) . '">' . Html::escape($type->key) . '</span>';
    }

    /** DataTables-Initialisierung (Suche, Sortierung nach Vor- und Nachname, Seitenlänge). */
    private function addJavascript(PagePresenter $page): void
    {
        $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.js');
        $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.css');

        $langCode = $this->context->l10n->getLanguageIsoCode();
        if ($langCode === '') {
            $langCode = 'en';
        }
        $languageUrl = json_encode(ADMIDIO_URL . FOLDER_LIBS . '/datatables/language/datatables.' . $langCode . '.json', JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $page->addJavascript('
            $("#' . self::TABLE_ID . '").DataTable({
                "language": {"url": ' . $languageUrl . '},
                "pageLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "alle"]],
                "order": [[1, "asc"], [0, "asc"]],
                "columnDefs": [{"targets": "adm-history-year", "orderable": false}],
                "fixedHeader": true,
                "responsive": false,
                "autoWidth": false
            });', true);
    }
}
