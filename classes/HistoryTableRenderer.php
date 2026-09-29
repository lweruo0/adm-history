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

    /** Filter nach aktueller Mitgliedschaft (GET-Parameter status) */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FORMER = 'former';
    public const STATUS_ALL = 'all';
    public const STATUS_OPTIONS = [self::STATUS_ACTIVE, self::STATUS_FORMER, self::STATUS_ALL];

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
     * (GET-Parameter years) und Legende der Mitgliedsarten.
     *
     * @param int    $selectedYears  aktuell gewählte Anzahl Jahre vor dem aktuellen Jahr (0 = alle)
     * @param string $selectedStatus aktuell gewählter Filter (eine der STATUS_*-Konstanten)
     */
    public function renderToolbar(PagePresenter $page, string $pluginUrl, int $selectedYears, string $selectedStatus): void
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
            . 'des jeweiligen Jahres. „Aktive Kontakte“ sind heute in einer der konfigurierten Rollen, „Ehemalige Kontakte“ waren es früher. '
            . 'Die Spalte „Zusatz“ zeigt die optionalen Beitragsrollen (Zusatzbeiträge) des Jahres. '
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
     * @param array<string, array<string, mixed>> $users         Personen aus HistoryLoader::loadAll()
     * @param int[]                               $years         anzuzeigende Jahre, absteigend
     * @param int[]                               $editableYears Jahre mit Auswahl (aktuelles Jahr und Folgejahr)
     * @param bool                                $canEdit       darf der Benutzer Mitgliedsarten ändern?
     * @param string                              $pluginUrl     URL von index.php (Ziel der Änderungen mit mode=change bzw. mode=addfee)
     * @param string                              $csrfToken     CSRF-Token der aktuellen Session
     */
    public function render(PagePresenter $page, array $users, array $years, array $editableYears, bool $canEdit, string $pluginUrl, string $csrfToken): void
    {
        $e = Html::escape(...);

        // Spalte „Zusatz“ (optionale Beitragsrollen) nur, wenn welche konfiguriert sind
        $showFees = $this->config->getOptionalFeeRoleNames() !== [];

        $rows = [];
        foreach ($users as $user) {
            $cells = [];
            $visible = false;
            foreach ($years as $year) {
                $state = $this->history->yearState($user['periods'], $year, $user['commonPeriods']);
                $inYear = $this->history->typesInYear($user['periods'], $year);
                $visible = $visible || $inYear !== [] || $state['unknown'];
                $editableYear = in_array($year, $editableYears, true);
                if ($canEdit && $editableYear) {
                    $cells[] = $this->selectCell($user, $year, $state);
                } else {
                    $cells[] = $this->historyCell($inYear, $state['unknown']);
                }
                if ($showFees && $editableYear) {
                    $cells[] = $this->feeCell($user, $year, $this->history->rolesInYear($user['feePeriods'], $year), $state['value'], $canEdit);
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
            if ($showFees && in_array($year, $editableYears, true)) {
                $headers .= '<th class="adm-history-year" title="Zusatzbeiträge ' . $year . ': optionale Beitragsrollen, in denen die Person im Jahr ist (einmalige nur im Jahr des Beginns)">'
                    . 'Zusatz ' . $year . '</th>';
            }
        }

        $page->addHtml('<style>
            .adm-history-badge { display: inline-block; min-width: 1.7em; padding: 0 .3em; margin: 0 1px; border-radius: .25rem;
                line-height: 1.3; text-align: center; font-weight: 600; color: #212529; border: 1px solid rgba(0,0,0,.15); }
            .adm-history-unknown { background-color: #e9ecef; color: #6c757d; }
            .adm-history-fee { display: inline-block; padding: 0 .3em; margin: 0 1px; border-radius: .25rem; line-height: 1.3;
                font-size: .85em; background-color: #f8f9fa; color: #212529; border: 1px solid rgba(0,0,0,.15); }
            #' . self::TABLE_ID . ' td.adm-history-fees { text-align: left; }
            .adm-history-fee-remove { border: 0; background: none; padding: 0 0 0 .3em; margin: 0; line-height: 1;
                font-size: 1.1em; color: #6c757d; cursor: pointer; vertical-align: baseline; }
            .adm-history-fee-remove:hover { color: #dc3545; }
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

        $this->addJavascript($page, $pluginUrl, $csrfToken);
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

        // nur die konfigurierten Wechsel anbieten; ohne Alternative ist die Auswahl gesperrt
        $allowed = $this->config->getAllowedTargets($state['value']);
        $locked = $allowed === [];
        $html = '<td class="adm-history-year">'
            . '<select class="form-select form-select-sm d-inline-block w-auto adm-history-select"'
            . ' data-user="' . $e($user['usr_uuid']) . '" data-year="' . $year . '" data-name="' . $e($name) . '"'
            . ' data-previous="' . $e($state['value']) . '" data-locked="' . ($locked ? '1' : '') . '"' . ($locked ? ' disabled' : '')
            . ' aria-label="Mitgliedsart ' . $year . ' von ' . $e($name) . '">';
        foreach (array_merge([''], array_keys($this->config->getTypes())) as $key) {
            if ($key !== $state['value'] && !in_array($key, $allowed, true)) {
                continue;
            }
            $html .= '<option value="' . $e($key) . '"' . ($state['value'] === $key ? ' selected' : '') . '>'
                . ($key === '' ? '–' : $e($key)) . '</option>';
        }
        $html .= '</select>' . $this->othersIcon($year, $state['others']) . ' ' . $this->unknownBadge(!$state['unknown']) . '</td>';

        return $html;
    }

    /**
     * Zelle „Zusatz“: optionale Beitragsrollen, in denen die Person im Jahr ist, und (mit Recht)
     * eine Auswahl „+“ mit den noch nicht vorhandenen Zusatzbeiträgen der Mitgliedsart am 31.12.
     * Die Zelle trägt Person und Jahr als data-Attribute, damit das JavaScript sie nach einer
     * Änderung aktualisiert.
     *
     * @param array<string, mixed> $user
     * @param string[]             $roleNames vorhandene Zusatzbeiträge des Jahres
     * @param string               $typeKey   Mitgliedsart am 31.12. des Jahres ('' = kein Mitglied)
     */
    private function feeCell(array $user, int $year, array $roleNames, string $typeKey, bool $canEdit): string
    {
        $e = Html::escape(...);
        $name = trim($user['first_name'] . ' ' . $user['last_name']);

        $html = '<td class="adm-history-fees" data-user="' . $e($user['usr_uuid']) . '" data-year="' . $year . '">'
            . '<span class="adm-history-fee-list">' . $this->feeBadges($roleNames, $canEdit) . '</span>';
        if ($canEdit) {
            $type = $this->config->getType($typeKey);
            $options = $type === null ? [] : array_values(array_diff($type->optionalFeeRoles, $roleNames));
            $html .= ' <select class="form-select form-select-sm d-inline-block w-auto adm-history-fee-add' . ($options === [] ? ' d-none' : '') . '"'
                . ' data-user="' . $e($user['usr_uuid']) . '" data-year="' . $year . '" data-name="' . $e($name) . '"'
                . ' title="Zusatzbeitrag ab 01.01.' . $year . ' hinzufügen" aria-label="Zusatzbeitrag ' . $year . ' für ' . $e($name) . ' hinzufügen">'
                . '<option value="">+</option>';
            foreach ($options as $roleName) {
                $html .= '<option value="' . $e($roleName) . '">' . $e($roleName) . '</option>';
            }
            $html .= '</select>';
        }

        return $html . '</td>';
    }

    /**
     * Kürzel der Zusatzbeiträge; mit Änderungsrecht trägt jedes ein „×“ zum Entfernen.
     * @param string[] $roleNames
     */
    private function feeBadges(array $roleNames, bool $removable): string
    {
        $html = '';
        foreach ($roleNames as $roleName) {
            $html .= '<span class="adm-history-fee">' . Html::escape($roleName);
            if ($removable) {
                $html .= '<button type="button" class="adm-history-fee-remove" data-role="' . Html::escape($roleName) . '"'
                    . ' title="Zusatzbeitrag „' . Html::escape($roleName) . '“ entfernen" aria-label="Zusatzbeitrag entfernen">&times;</button>';
            }
            $html .= '</span>';
        }

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

    /**
     * DataTables-Initialisierung und Verarbeitung der Auswahlfelder: Wechsel der Mitgliedsart
     * (mode=change) und Hinzufügen eines Zusatzbeitrags (mode=addfee), jeweils per fetch an index.php.
     */
    private function addJavascript(PagePresenter $page, string $pluginUrl, string $csrfToken): void
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
            $types[$type->key] = [
                'name'         => $type->name,
                'color'        => $type->color,
                'label'        => $type->label(),
                'optionalFees' => $type->optionalFeeRoles,
            ];
        }
        $settings = json_encode([
            'changeUrl'    => $pluginUrl . '?mode=change',
            'addFeeUrl'    => $pluginUrl . '?mode=addfee',
            'removeFeeUrl' => $pluginUrl . '?mode=removefee',
            'csrf'        => $csrfToken,
            'types'       => $types,
            'transitions' => $this->config->getAllTransitions(),
            'language'    => $languageUrl,
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

                // Optionen neu aufbauen: bisheriger Wert plus die dafür konfigurierten Wechsel
                function buildOptions(select, value) {
                    var allowed = settings.transitions[value] || [];
                    select.innerHTML = "";
                    [""].concat(Object.keys(settings.types)).forEach(function (key) {
                        if (key !== value && allowed.indexOf(key) === -1) {
                            return;
                        }
                        var option = document.createElement("option");
                        option.value = key;
                        option.textContent = key === "" ? "–" : key;
                        option.selected = key === value;
                        select.appendChild(option);
                    });
                    select.dataset.locked = allowed.length === 0 ? "1" : "";
                }

                // Zelle „Zusatz“ neu füllen: vorhandene Beitragsrollen des Jahres und die
                // Auswahl „+“ mit den noch fehlenden Zusatzbeiträgen der Mitgliedsart
                function updateFees(cell, roleNames, typeKey) {
                    var add = cell.querySelector(".adm-history-fee-add");
                    var list = cell.querySelector(".adm-history-fee-list");
                    list.innerHTML = "";
                    roleNames.forEach(function (roleName) {
                        var badge = document.createElement("span");
                        badge.className = "adm-history-fee";
                        badge.textContent = roleName;
                        if (add) {
                            // mit Änderungsrecht (Auswahl „+“ vorhanden) auch „×“ zum Entfernen
                            var remove = document.createElement("button");
                            remove.type = "button";
                            remove.className = "adm-history-fee-remove";
                            remove.dataset.role = roleName;
                            remove.title = "Zusatzbeitrag „" + roleName + "“ entfernen";
                            remove.setAttribute("aria-label", "Zusatzbeitrag entfernen");
                            remove.innerHTML = "&times;";
                            badge.appendChild(remove);
                        }
                        list.appendChild(badge);
                    });
                    if (!add) {
                        return;
                    }
                    var type = settings.types[typeKey];
                    var options = type ? type.optionalFees.filter(function (name) { return roleNames.indexOf(name) === -1; }) : [];
                    add.innerHTML = "";
                    var placeholder = document.createElement("option");
                    placeholder.value = "";
                    placeholder.textContent = "+";
                    add.appendChild(placeholder);
                    options.forEach(function (name) {
                        var option = document.createElement("option");
                        option.value = name;
                        option.textContent = name;
                        add.appendChild(option);
                    });
                    add.value = "";
                    add.classList.toggle("d-none", options.length === 0);
                }

                // Antwort des Servers (Stand beider änderbaren Jahre) in die Zeile übernehmen
                function applyResponse(userUuid, data) {
                    Object.keys(data.years).forEach(function (year) {
                        var select = table.querySelector(".adm-history-select[data-user=\"" + userUuid + "\"][data-year=\"" + year + "\"]");
                        if (select) {
                            updateSelect(select, data.years[year]);
                        }
                        var cell = table.querySelector(".adm-history-fees[data-user=\"" + userUuid + "\"][data-year=\"" + year + "\"]");
                        if (cell && data.fees && data.fees[year]) {
                            updateFees(cell, data.fees[year], data.years[year].value);
                        }
                    });
                }

                function post(url, fields) {
                    var body = new URLSearchParams(fields);
                    body.append("adm_csrf_token", settings.csrf);
                    return fetch(url, {method: "POST", body: body, headers: {"X-Requested-With": "XMLHttpRequest"}, credentials: "same-origin"})
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.status !== "ok") {
                                throw new Error(data.message || "Unbekannter Fehler");
                            }
                            return data;
                        });
                }

                function updateSelect(select, state) {
                    buildOptions(select, state.value);
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
                    post(settings.changeUrl, {user_uuid: select.dataset.user, year: select.dataset.year, type: select.value})
                        .then(function (data) {
                            applyResponse(select.dataset.user, data);
                        })
                        .catch(function (error) {
                            select.value = previous;
                            applyColor(select);
                            window.alert("Die Änderung wurde nicht gespeichert: " + error.message);
                        })
                        .finally(function () {
                            select.disabled = select.dataset.locked === "1";
                        });
                });

                // Zusatzbeitrag hinzufügen (Auswahl „+“ in der Spalte „Zusatz“)
                table.addEventListener("change", function (event) {
                    var select = event.target;
                    if (!select.classList.contains("adm-history-fee-add") || select.value === "") {
                        return;
                    }
                    var roleName = select.value;
                    var question = select.dataset.name + ": Zusatzbeitrag „" + roleName + "“ ab 01.01." + select.dataset.year + " hinzufügen?";
                    if (!window.confirm(question)) {
                        select.value = "";
                        return;
                    }

                    select.disabled = true;
                    post(settings.addFeeUrl, {user_uuid: select.dataset.user, year: select.dataset.year, role: roleName})
                        .then(function (data) {
                            applyResponse(select.dataset.user, data);
                        })
                        .catch(function (error) {
                            select.value = "";
                            window.alert("Der Zusatzbeitrag wurde nicht gespeichert: " + error.message);
                        })
                        .finally(function () {
                            select.disabled = false;
                        });
                });

                // Zusatzbeitrag entfernen („×“ am Kürzel in der Spalte „Zusatz“)
                table.addEventListener("click", function (event) {
                    var button = event.target.closest(".adm-history-fee-remove");
                    if (!button) {
                        return;
                    }
                    var cell = button.closest(".adm-history-fees");
                    var roleName = button.dataset.role;
                    var add = cell.querySelector(".adm-history-fee-add");
                    var name = add ? add.dataset.name : "";
                    var question = name + ": Zusatzbeitrag „" + roleName + "“ ab 01.01." + cell.dataset.year + " entfernen?";
                    if (!window.confirm(question)) {
                        return;
                    }

                    button.disabled = true;
                    post(settings.removeFeeUrl, {user_uuid: cell.dataset.user, year: cell.dataset.year, role: roleName})
                        .then(function (data) {
                            applyResponse(cell.dataset.user, data);
                        })
                        .catch(function (error) {
                            button.disabled = false;
                            window.alert("Der Zusatzbeitrag wurde nicht entfernt: " + error.message);
                        });
                });
            })();', true);
    }
}
