<?php
namespace AdmHistory;

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;

/**
 * Stellt den Schnellwechsel dar: je Person eine Zeile mit Vorname, Nachname und einer Spalte je
 * änderbarem Jahr (aktuelles Jahr und Folgejahr). Jede Jahresspalte zeigt einen Button je
 * Mitgliedsart, einen Button „Kündigung“ (kein Mitglied) und die optionalen Beitragsrollen
 * (Zusatzbeiträge) der Mitgliedsart am 31.12. als Umschalter.
 *
 * Die Buttons baut das JavaScript aus dem Zustand je Person und Jahr (YearHistory::yearStateWithFees())
 * auf, damit Erstanzeige und Aktualisierung nach dem Speichern denselben Code nutzen. Gespeichert
 * wird per fetch() an quick_change.php?mode=save als JSON (user_uuid, year, art, fee wie bei
 * MembershipApi::change()); die Antwort enthält den neuen Stand beider Jahre.
 */
final class QuickChangeRenderer
{
    private const TABLE_ID = 'adm_history_quick_table';

    public function __construct(
        private readonly AdmidioContext $context,
        private readonly MembershipTypeConfig $config,
        private readonly YearHistory $history,
    ) {
    }

    /**
     * Hinweis, Tabelle aller übergebenen Personen und JavaScript.
     *
     * @param array<array-key, array<string, mixed>> $users         Personen aus HistoryLoader::loadAll()
     * @param int[]                                  $editableYears aktuelles Jahr und Folgejahr
     * @param string                                 $saveUrl       Ziel der Änderungen (quick_change.php?mode=save)
     * @param string                                 $csrfToken     CSRF-Token der aktuellen Session
     */
    public function render(PagePresenter $page, array $users, array $editableYears, string $saveUrl, string $csrfToken): void
    {
        $e = Html::escape(...);

        $page->addHtml('<div class="alert alert-secondary" role="alert"><i class="bi bi-info-circle"></i> '
            . 'Je Jahr ist die Mitgliedsart am 31.12. hervorgehoben. Ein Klick auf eine andere Mitgliedsart oder auf „Kündigung“ '
            . 'ändert die Mitgliedschaft ab dem 1. Januar des Jahres (nur die in mitgliedsarten.php erlaubten Wechsel sind aktiv). '
            . 'Darunter stehen die Zusatzbeiträge der Mitgliedsart; ein Klick schaltet sie für das Jahr ein oder aus.</div>');

        $states = [];
        $rows = [];
        foreach ($users as $user) {
            $uuid = (string) $user['usr_uuid'];
            $name = trim($user['first_name'] . ' ' . $user['last_name']);
            $years = [];
            $cells = '';
            foreach ($editableYears as $year) {
                $years[(string) $year] = $this->history->yearStateWithFees($user['periods'], $user['commonPeriods'], $user['feePeriods'], $year);
                $cells .= '<td class="adm-quick-year" data-user="' . $e($uuid) . '" data-year="' . $year . '"></td>';
            }
            $states[$uuid] = ['name' => $name, 'years' => $years];

            $profileUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', ['user_uuid' => $uuid]);
            $rows[] = '<tr data-user="' . $e($uuid) . '">'
                . '<td><a href="' . $profileUrl . '">' . $e($user['first_name']) . '</a></td>'
                . '<td><a href="' . $profileUrl . '">' . $e($user['last_name']) . '</a></td>'
                . $cells . '</tr>';
        }

        if ($rows === []) {
            $page->addHtml('<div class="alert alert-info" role="alert">Im aktuellen Jahr und im Folgejahr gibt es keine Mitglieder.</div>');
            return;
        }

        $headers = '<th>Vorname</th><th>Nachname</th>';
        foreach ($editableYears as $index => $year) {
            $headers .= '<th class="adm-quick-year">' . $year . ' <span class="fw-normal text-muted small">('
                . ($index === 0 ? 'aktuelles Jahr' : 'Folgejahr') . ', ab 01.01.' . $year . ')</span></th>';
        }

        $page->addHtml('<style>
            #' . self::TABLE_ID . ' td, #' . self::TABLE_ID . ' th { vertical-align: middle; }
            #' . self::TABLE_ID . ' td:nth-child(-n+2) { white-space: nowrap; }
            #' . self::TABLE_ID . ' th.adm-quick-year { cursor: default; }
            #' . self::TABLE_ID . ' th.adm-quick-year::before, #' . self::TABLE_ID . ' th.adm-quick-year::after { display: none; }
            .adm-quick-types, .adm-quick-fees { display: flex; flex-wrap: wrap; gap: .25rem; align-items: center; }
            .adm-quick-fees { margin-top: .25rem; }
            .adm-quick-fees:empty { display: none; }
            .adm-quick-unknown { display: inline-block; min-width: 1.7em; border-radius: .25rem; text-align: center; font-weight: 600;
                background-color: #e9ecef; color: #6c757d; border: 1px solid rgba(0,0,0,.15); }
            .adm-quick-saved { animation: adm-quick-flash 1.2s ease-out; }
            @keyframes adm-quick-flash { from { background-color: #d1e7dd; } to { background-color: transparent; } }
        </style>');

        $page->addHtml('<div class="table-responsive"><table id="' . self::TABLE_ID . '" class="table table-sm table-hover w-100">'
            . '<thead><tr>' . $headers . '</tr></thead><tbody>' . implode('', $rows) . '</tbody></table></div>');

        $this->addJavascript($page, $states, $saveUrl, $csrfToken);
    }

    /**
     * Aufbau der Buttons, DataTables-Initialisierung und Speichern per fetch.
     *
     * @param array<string, array{name:string, years:array<int, array{value:string, others:string[], unknown:bool, fees:string[]}>}> $states
     *        Zustand je Person (UUID) und Jahr
     */
    private function addJavascript(PagePresenter $page, array $states, string $saveUrl, string $csrfToken): void
    {
        $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.js');
        $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/datatables/datatables.css');

        $langCode = $this->context->l10n->getLanguageIsoCode();
        if ($langCode === '') {
            $langCode = 'en';
        }

        $types = [];
        foreach ($this->config->getTypes() as $type) {
            $types[$type->key] = [
                'name'         => $type->name,
                'label'        => $type->label(),
                'optionalFees' => $type->optionalFeeRoles,
            ];
        }
        $commonRoles = $this->config->getCommonRoles();
        $settings = json_encode([
            'saveUrl'        => $saveUrl,
            'csrf'           => $csrfToken,
            'types'          => $types,
            'transitions'    => $this->config->getAllTransitions(),
            'entryFromTypes' => $this->config->getEntryFromTypes(),
            'entryHint'      => $this->config->getEntryHint(),
            'unknownTitle'   => 'Mitglied in ' . ($commonRoles === [] ? 'einer gemeinsamen Rolle' : '„' . implode('“, „', $commonRoles) . '“')
                . ', aber Mitgliedsart nicht ermittelbar',
            'language'       => ADMIDIO_URL . FOLDER_LIBS . '/datatables/language/datatables.' . $langCode . '.json',
            'states'         => $states,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $page->addJavascript('
            (function () {
                var settings = ' . $settings . ';
                var table = document.getElementById("' . self::TABLE_ID . '");

                function labelOf(key) {
                    return key === "" ? "kein Mitglied" : settings.types[key].label;
                }

                function stateOf(cell) {
                    return settings.states[cell.dataset.user].years[cell.dataset.year];
                }

                var toggleCount = 0;

                // Umschalter im Stil des Admidio-Themes: input.btn-check mit label.btn (ausgewählt = dunkel)
                function toggle(container, inputType, groupName, text, title, checked, disabled) {
                    var id = "adm_quick_toggle_" + (++toggleCount);
                    var input = document.createElement("input");
                    input.type = inputType;
                    input.className = "btn-check";
                    input.id = id;
                    input.name = groupName;
                    input.autocomplete = "off";
                    input.checked = checked;
                    input.disabled = disabled;
                    var label = document.createElement("label");
                    label.className = "btn btn-sm btn-outline-primary";
                    label.htmlFor = id;
                    label.textContent = text;
                    label.title = title;
                    container.appendChild(input);
                    container.appendChild(label);
                    return input;
                }

                // Zelle eines Jahres aus dem Zustand aufbauen: Mitgliedsarten, Kündigung, Hinweise, Zusatzbeiträge
                function renderCell(cell) {
                    var state = stateOf(cell);
                    var year = cell.dataset.year;
                    var allowed = settings.transitions[state.value] || [];
                    var groupName = "adm_quick_" + cell.dataset.user + "_" + year;
                    cell.innerHTML = "";

                    var types = document.createElement("div");
                    types.className = "adm-quick-types";
                    types.setAttribute("role", "radiogroup");
                    types.setAttribute("aria-label", "Mitgliedsart " + year);
                    Object.keys(settings.types).concat([""]).forEach(function (key) {
                        var active = key === state.value;
                        var text = key === "" ? "Kündigung" : settings.types[key].name;
                        var title = key === ""
                            ? (active ? "Kein Mitglied am 31.12." + year : "Kündigung: kein Mitglied ab 01.01." + year)
                            : settings.types[key].label + (active ? " am 31.12." + year : " ab 01.01." + year);
                        var input = toggle(types, "radio", groupName, text, title, active, !active && allowed.indexOf(key) === -1);
                        input.classList.add("adm-quick-type");
                        input.value = key;
                    });
                    if (state.others.length > 0) {
                        var icon = document.createElement("i");
                        icon.className = "bi bi-exclamation-triangle-fill text-warning";
                        icon.title = "Im Jahr " + year + " außerdem: " + state.others.map(labelOf).join(", ");
                        types.appendChild(icon);
                    }
                    if (state.unknown) {
                        var unknown = document.createElement("span");
                        unknown.className = "adm-quick-unknown";
                        unknown.textContent = "?";
                        unknown.title = settings.unknownTitle;
                        types.appendChild(unknown);
                    }
                    cell.appendChild(types);

                    // Zusatzbeiträge der Mitgliedsart am 31.12., dazu vorhandene, die nicht (mehr) zu ihr gehören
                    var fees = document.createElement("div");
                    fees.className = "adm-quick-fees";
                    var type = settings.types[state.value];
                    var names = type ? type.optionalFees.slice() : [];
                    state.fees.forEach(function (name) {
                        if (names.indexOf(name) === -1) {
                            names.push(name);
                        }
                    });
                    names.forEach(function (name) {
                        var active = state.fees.indexOf(name) !== -1;
                        // ohne Mitgliedsart nur Anzeige: die API würde art = "" als Kündigung ausführen
                        var input = toggle(fees, "checkbox", "", name,
                            !type ? "Zusatzbeitrag „" + name + "“ (ohne Mitgliedsart am 31.12." + year + " nicht änderbar)"
                                : "Zusatzbeitrag „" + name + "“ " + (active ? "entfernen" : "ab 01.01." + year + " hinzufügen"),
                            active, !type);
                        input.classList.add("adm-quick-fee");
                        input.value = name;
                    });
                    cell.appendChild(fees);
                }

                function rowCells(userUuid) {
                    return table.querySelectorAll(".adm-quick-year[data-user=\"" + userUuid + "\"]");
                }

                // Speichern über MembershipApi; die Antwort enthält den neuen Stand beider Jahre
                function save(cell, art, fees) {
                    var userUuid = cell.dataset.user;
                    var cells = rowCells(userUuid);
                    cells.forEach(function (rowCell) {
                        rowCell.querySelectorAll("input").forEach(function (input) { input.disabled = true; });
                    });
                    fetch(settings.saveUrl, {
                        method: "POST",
                        headers: {"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
                        credentials: "same-origin",
                        body: JSON.stringify({
                            user_uuid: userUuid,
                            year: parseInt(cell.dataset.year, 10),
                            art: art,
                            fee: fees,
                            adm_csrf_token: settings.csrf
                        })
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.status !== "ok") {
                                throw new Error(data.message || "Unbekannter Fehler");
                            }
                            Object.keys(data.years).forEach(function (year) {
                                settings.states[userUuid].years[year] = data.years[year];
                            });
                            cells.forEach(function (rowCell) {
                                renderCell(rowCell);
                                rowCell.classList.remove("adm-quick-saved");
                                void rowCell.offsetWidth;
                                rowCell.classList.add("adm-quick-saved");
                            });
                        })
                        .catch(function (error) {
                            cells.forEach(renderCell);
                            window.alert("Die Änderung wurde nicht gespeichert: " + error.message);
                        });
                }

                // Hinweis, wenn ein Speichern die Mitgliedsart ab 01.01. für das ganze Jahr festschreibt
                function othersHint(state, year) {
                    return state.others.length > 0
                        ? "\n\nAchtung: Im Jahr " + year + " gab es außerdem " + state.others.map(labelOf).join(", ")
                            + ". " + labelOf(state.value) + " gilt danach ab 01.01." + year + "."
                        : "";
                }

                table.querySelectorAll(".adm-quick-year[data-user]").forEach(renderCell);

                $(table).DataTable({
                    "language": {"url": settings.language},
                    "pageLength": 50,
                    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "alle"]],
                    "order": [[1, "asc"], [0, "asc"]],
                    "columnDefs": [{"targets": "adm-quick-year", "orderable": false, "searchable": false}],
                    "responsive": false,
                    "autoWidth": false
                });

                // Auswahl einer Mitgliedsart oder eines Zusatzbeitrags; bei Abbruch wird die Zelle zurückgesetzt
                table.addEventListener("change", function (event) {
                    var element = event.target;
                    if (!element.classList.contains("adm-quick-type") && !element.classList.contains("adm-quick-fee")) {
                        return;
                    }
                    var cell = element.closest(".adm-quick-year");
                    var state = stateOf(cell);
                    var year = cell.dataset.year;
                    var name = settings.states[cell.dataset.user].name;
                    var question;

                    if (element.classList.contains("adm-quick-type")) {
                        var art = element.value;
                        if (art === state.value) {
                            return;
                        }
                        question = art === ""
                            ? name + ": Kündigung zum 31.12." + (parseInt(year, 10) - 1) + ", kein Mitglied ab 01.01." + year + "?"
                            : name + ": Mitgliedsart ab 01.01." + year + " von " + labelOf(state.value) + " auf " + labelOf(art) + " ändern?";
                        if (art !== "" && settings.entryFromTypes.indexOf(state.value) !== -1 && settings.entryHint !== "") {
                            question += "\n\nVereinsbeitritt: " + settings.entryHint.split("{date}").join("01.01." + year);
                        }
                        if (!window.confirm(question)) {
                            renderCell(cell);
                            return;
                        }
                        // Zusatzbeiträge bleiben, soweit sie auch zur neuen Mitgliedsart gehören
                        var newType = settings.types[art];
                        var keep = newType ? state.fees.filter(function (fee) { return newType.optionalFees.indexOf(fee) !== -1; }) : [];
                        save(cell, art, keep);
                        return;
                    }

                    var fee = element.value;
                    var active = state.fees.indexOf(fee) !== -1;
                    question = name + ": Zusatzbeitrag „" + fee + "“ ab 01.01." + year + (active ? " entfernen?" : " hinzufügen?")
                        + othersHint(state, year);
                    if (!window.confirm(question)) {
                        renderCell(cell);
                        return;
                    }
                    var fees = active
                        ? state.fees.filter(function (name) { return name !== fee; })
                        : state.fees.concat([fee]);
                    save(cell, state.value, fees);
                });
            })();', true);
    }
}
