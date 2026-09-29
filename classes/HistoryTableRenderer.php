<?php
namespace AdmHistory;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;

/**
 * Stellt die Mitglieder-Historie als Tabelle dar: Vorname, Nachname und eine Spalte je Jahr
 * (absteigend). Vergangene Jahre zeigen die Mitgliedsarten als farbige Kürzel; für das aktuelle
 * Jahr und das Folgejahr wird eine Auswahl angeboten, über die die Mitgliedsart ab dem 1. Januar
 * des jeweiligen Jahres geändert werden kann (nur mit entsprechendem Recht).
 *
 * Die Tabelle wird mit der von Admidio mitgelieferten DataTables-Bibliothek durchsuchbar und nach
 * Vor- und Nachname sortierbar gemacht (die Jahresspalten sind nicht sortierbar, die Zeilen sind
 * kompakt); der Wechsel wird per fetch() an index.php?mode=change gesendet.
 */
final class HistoryTableRenderer
{
    private const TABLE_ID = 'adm_history_table';

    /** Auswahlmöglichkeiten für „Jahre zurück“; 0 = alle */
    private const YEAR_OPTIONS = [5, 10, 15, 20, 30, 50, 0];

    public function __construct(
        private readonly AdmidioContext $context,
        private readonly MembershipTypeConfig $config,
        private readonly YearHistory $history,
    ) {
    }

    /**
     * Auswahl „Jahre zurück“ (GET-Parameter years) und Legende der Mitgliedsarten.
     *
     * @param int $selectedYears aktuell gewählte Anzahl Jahre vor dem aktuellen Jahr (0 = alle)
     */
    public function renderToolbar(PagePresenter $page, string $pluginUrl, int $selectedYears): void
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
            . '<form method="get" action="' . $e($pluginUrl) . '" id="adm_history_years_form" class="row g-2 align-items-end">'
            . '<div class="col-12 col-sm-auto">'
            . '<label for="adm_history_years" class="form-label fw-bold">Jahre zurück</label>'
            . '<select class="form-select" id="adm_history_years" name="years">';
        foreach ($options as $years) {
            $html .= '<option value="' . $years . '"' . ($years === $selectedYears ? ' selected' : '') . '>'
                . ($years === 0 ? 'alle Jahre' : $years . ' Jahre') . '</option>';
        }
        $html .= '</select></div>'
            . '<div class="col-12 col-sm"><div class="fw-bold form-label">Mitgliedsarten</div><div>';
        foreach ($this->config->getTypes() as $type) {
            $html .= $this->badge($type) . ' <span class="me-3">' . $e($type->name)
                . ' <span class="text-muted small">(' . $e(implode(', ', $type->roleNames)) . ')</span></span>';
        }
        if ($this->config->getCommonRoles() !== []) {
            $html .= $this->unknownBadge(false) . ' <span class="me-3">' . $e($this->unknownTitle()) . '</span>';
        }
        $html .= '</div></div>'
            . '<div class="col-12 form-text">Je Jahr stehen alle Mitgliedsarten, in denen die Person in diesem Jahr mindestens einen Tag war. '
            . 'Für das aktuelle Jahr und das Folgejahr zeigt die Auswahl die Mitgliedsart am 31.12.; eine Änderung gilt ab dem 1. Januar '
            . 'des jeweiligen Jahres. Personen ohne Mitgliedsart im angezeigten Zeitraum werden ausgeblendet.</div>'
            . '</form></div></div>';

        $page->addHtml($html);
        $page->addJavascript('
            document.getElementById("adm_history_years").addEventListener("change", function () {
                document.getElementById("adm_history_years_form").submit();
            });', true);
    }

    /**
     * Tabelle mit allen Personen.
     *
     * @param array<string, array<string, mixed>> $users         Personen aus HistoryLoader::loadAll()
     * @param int[]                               $years         anzuzeigende Jahre, absteigend
     * @param int[]                               $editableYears Jahre mit Auswahl (aktuelles Jahr und Folgejahr)
     * @param bool                                $canEdit       darf der Benutzer Mitgliedsarten ändern?
     * @param string                              $changeUrl     Ziel der Änderung (index.php?mode=change)
     * @param string                              $csrfToken     CSRF-Token der aktuellen Session
     */
    public function render(PagePresenter $page, array $users, array $years, array $editableYears, bool $canEdit, string $changeUrl, string $csrfToken): void
    {
        $e = Html::escape(...);

        $rows = [];
        foreach ($users as $user) {
            $cells = [];
            $visible = false;
            foreach ($years as $year) {
                $state = $this->history->yearState($user['periods'], $year, $user['commonPeriods']);
                $inYear = $this->history->typesInYear($user['periods'], $year);
                $visible = $visible || $inYear !== [] || $state['unknown'];
                if ($canEdit && in_array($year, $editableYears, true)) {
                    $cells[] = $this->selectCell($user, $year, $state);
                } else {
                    $cells[] = $this->historyCell($inYear, $state['unknown']);
                }
            }
            if (!$visible) {
                continue;
            }

            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $user['usr_uuid']]);
            $rows[] = '<tr data-user="' . $e($user['usr_uuid']) . '">'
                . '<td><a href="' . $profileUrl . '">' . $e($user['first_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($user['last_name']) . '</a></td>'
                . implode('', $cells) . '</tr>';
        }

        $headers = '<th>Vorname</th><th>Nachname</th>';
        foreach ($years as $year) {
            $editable = $canEdit && in_array($year, $editableYears, true);
            $headers .= '<th class="text-center adm-history-year"' . ($editable ? ' title="Änderbar: Mitgliedsart ab 01.01.' . $year . '"' : '') . '>'
                . $year . ($editable ? ' <i class="bi bi-pencil-square small"></i>' : '') . '</th>';
        }

        $page->addHtml('<style>
            .adm-history-badge { display: inline-block; min-width: 1.7em; padding: 0 .3em; margin: 0 1px; border-radius: .25rem;
                line-height: 1.3; text-align: center; font-weight: 600; color: #212529; border: 1px solid rgba(0,0,0,.15); }
            .adm-history-unknown { background-color: #e9ecef; color: #6c757d; }
            .adm-history-select { min-width: 4.5em; padding-top: 0; padding-bottom: 0; line-height: 1.3; font-weight: 600; }
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

        $this->addJavascript($page, $changeUrl, $csrfToken);
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
            $badges .= $this->unknownBadge(false);
        }

        return '<td class="adm-history-year">' . $badges . '</td>';
    }

    /**
     * Zelle eines änderbaren Jahres: Auswahl mit der Mitgliedsart am 31.12.; weitere Mitgliedsarten
     * des Jahres werden als Warnsymbol mit Tooltip angezeigt, „Mitglied ohne Mitgliedsart“ als „?“.
     *
     * @param array<string, mixed>                              $user
     * @param array{value:string, others:string[], unknown:bool} $state
     */
    private function selectCell(array $user, int $year, array $state): string
    {
        $e = Html::escape(...);
        $name = trim($user['first_name'] . ' ' . $user['last_name']);

        $html = '<td class="adm-history-year">'
            . '<select class="form-select form-select-sm d-inline-block w-auto adm-history-select"'
            . ' data-user="' . $e($user['usr_uuid']) . '" data-year="' . $year . '" data-name="' . $e($name) . '"'
            . ' data-previous="' . $e($state['value']) . '" aria-label="Mitgliedsart ' . $year . ' von ' . $e($name) . '">'
            . '<option value=""' . ($state['value'] === '' ? ' selected' : '') . '>–</option>';
        foreach ($this->config->getTypes() as $type) {
            $html .= '<option value="' . $e($type->key) . '"' . ($state['value'] === $type->key ? ' selected' : '') . '>'
                . $e($type->key) . '</option>';
        }
        $html .= '</select>' . $this->othersIcon($year, $state['others']) . ' ' . $this->unknownBadge(!$state['unknown']) . '</td>';

        return $html;
    }

    /**
     * Kennzeichen „?“: Mitglied in einer gemeinsamen Rolle, aber ohne ermittelbare Mitgliedsart.
     * In änderbaren Zellen wird es immer ausgegeben und bei Bedarf verborgen, damit das JavaScript
     * es nach einer Änderung ein- oder ausblenden kann.
     */
    private function unknownBadge(bool $hidden): string
    {
        return '<span class="adm-history-badge adm-history-unknown' . ($hidden ? ' d-none' : '') . '" title="'
            . Html::escape($this->unknownTitle()) . '">?</span>';
    }

    /** Erklärung des Kennzeichens „?“ (Tooltip und Legende). */
    private function unknownTitle(): string
    {
        $roles = $this->config->getCommonRoles();
        $roleText = $roles === [] ? 'einer gemeinsamen Rolle' : 'der Rolle „' . implode('“, „', $roles) . '“';

        return 'Mitglied in ' . $roleText . ', aber Mitgliedsart nicht ermittelbar';
    }

    /** Warnsymbol für weitere Mitgliedsarten im Jahr; ohne weitere Mitgliedsarten unsichtbar. */
    private function othersIcon(int $year, array $others): string
    {
        $labels = [];
        foreach ($others as $typeKey) {
            $type = $this->config->getType($typeKey);
            $labels[] = $type !== null ? $type->label() : $typeKey;
        }
        $title = $labels === [] ? '' : 'Im Jahr ' . $year . ' außerdem: ' . implode(', ', $labels);

        return ' <i class="bi bi-exclamation-triangle-fill text-warning adm-history-others' . ($labels === [] ? ' d-none' : '')
            . '" title="' . Html::escape($title) . '"></i>';
    }

    /** Farbiges Kürzel einer Mitgliedsart. */
    private function badge(MembershipType $type): string
    {
        return '<span class="adm-history-badge" style="background-color: ' . Html::escape($type->color) . '" title="'
            . Html::escape($type->name) . '">' . Html::escape($type->key) . '</span>';
    }

    /** DataTables-Initialisierung und Verarbeitung der Auswahl (Wechsel per fetch an index.php). */
    private function addJavascript(PagePresenter $page, string $changeUrl, string $csrfToken): void
    {
        $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.js');
        $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.css');

        $langCode = $this->context->l10n->getLanguageIsoCode();
        if ($langCode === '') {
            $langCode = 'en';
        }
        $languageUrl = ADMIDIO_URL . FOLDER_LIBS . '/datatables/language/datatables.' . $langCode . '.json';

        $types = [];
        foreach ($this->config->getTypes() as $type) {
            $types[$type->key] = ['name' => $type->name, 'color' => $type->color, 'label' => $type->label()];
        }
        $settings = json_encode([
            'url'      => $changeUrl,
            'csrf'     => $csrfToken,
            'types'    => $types,
            'language' => $languageUrl,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $page->addJavascript('
            (function () {
                var settings = ' . $settings . ';
                var table = document.getElementById("' . self::TABLE_ID . '");

                $(table).DataTable({
                    "language": {"url": settings.language},
                    "pageLength": 50,
                    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "alle"]],
                    "order": [[1, "asc"], [0, "asc"]],
                    "columnDefs": [{"targets": "adm-history-year", "orderable": false}],
                    "fixedHeader": true,
                    "responsive": false,
                    "autoWidth": false
                });

                function applyColor(select) {
                    var type = settings.types[select.value];
                    select.style.backgroundColor = type ? type.color : "";
                }

                function labelOf(key) {
                    return key === "" ? "kein Mitglied" : settings.types[key].label;
                }

                function updateSelect(select, state) {
                    select.value = state.value;
                    select.dataset.previous = state.value;
                    applyColor(select);
                    var icon = select.parentNode.querySelector(".adm-history-others");
                    if (icon) {
                        var labels = state.others.map(labelOf);
                        icon.title = labels.length ? "Im Jahr " + select.dataset.year + " außerdem: " + labels.join(", ") : "";
                        icon.classList.toggle("d-none", labels.length === 0);
                    }
                    var unknown = select.parentNode.querySelector(".adm-history-unknown");
                    if (unknown) {
                        unknown.classList.toggle("d-none", !state.unknown);
                    }
                }

                table.querySelectorAll(".adm-history-select").forEach(applyColor);

                table.addEventListener("change", function (event) {
                    var select = event.target;
                    if (!select.classList.contains("adm-history-select")) {
                        return;
                    }
                    var previous = select.dataset.previous;
                    var question = select.dataset.name + ": Mitgliedsart ab 01.01." + select.dataset.year
                        + " von " + labelOf(previous) + " auf " + labelOf(select.value) + " ändern?";
                    if (!window.confirm(question)) {
                        select.value = previous;
                        return;
                    }

                    select.disabled = true;
                    var body = new URLSearchParams({
                        adm_csrf_token: settings.csrf,
                        user_uuid: select.dataset.user,
                        year: select.dataset.year,
                        type: select.value
                    });
                    fetch(settings.url, {method: "POST", body: body, headers: {"X-Requested-With": "XMLHttpRequest"}, credentials: "same-origin"})
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.status !== "ok") {
                                throw new Error(data.message || "Unbekannter Fehler");
                            }
                            Object.keys(data.years).forEach(function (year) {
                                var other = table.querySelector(".adm-history-select[data-user=\"" + select.dataset.user + "\"][data-year=\"" + year + "\"]");
                                if (other) {
                                    updateSelect(other, data.years[year]);
                                }
                            });
                        })
                        .catch(function (error) {
                            select.value = previous;
                            applyColor(select);
                            window.alert("Die Änderung wurde nicht gespeichert: " + error.message);
                        })
                        .finally(function () {
                            select.disabled = false;
                        });
                });
            })();', true);
    }
}
