<?php
/**
 * Admidio-Plugin: Mitglieder-Historie – Kacheln für die Admidio-Übersichtsseite
 *
 * Zeigt je Jahr (Vorjahr, aktuelles Jahr, Folgejahr) eine kleine Tabelle, wie viele Personen am
 * 1. Januar und am 31. Dezember in den konfigurierten Rollen („overviewRoles“ in mitgliedsarten.php)
 * waren. Gab es im Jahr Ein- oder Austritte, öffnet ein Button in der vierten Spalte die Liste dieser
 * Personen in einer neuen Registerkarte (mode=changes); unter der Tabelle stehen die Buttons
 * „Eintritte“ und „Austritte“ für alle Rollen der Kachel zusammen. Das HTML entsteht ohne
 * Smarty-Vorlage direkt in PHP (OverviewTileRenderer). Eingebunden wird die Datei in der Vorlage
 * system/overview.tpl des Themes:
 *
 *   {load_admidio_plugin plugin="adm-history" file="overview.php" year="next"}
 *
 * Das Attribut „year“ wählt die Kachel: „previous“ (Vorjahr), „current“ (aktuelles Jahr), „next“
 * (Folgejahr) oder „all“ (alle drei Tabellen untereinander, Standard). Admidio stellt die Attribute
 * der Smarty-Funktion beim Einbinden als $params bereit; beim direkten Aufruf der Datei im Browser
 * gilt der GET-Parameter „year“.
 *
 * Sehen dürfen die Zahlen nur angemeldete Administratoren; allen anderen wird nichts ausgegeben,
 * die Kachel bleibt dann leer (zum Ausblenden siehe README).
 *
 * Parameter:
 *   mode  (GET)  „changes“: Liste der Ein- und Austritte einer Kachelzeile als eigene Seite,
 *                sonst Kachel(n); nur beim direkten Aufruf, nicht beim Einbinden
 *   year  (Smarty-Attribut oder GET)  Kachel: „previous“, „current“, „next“ oder „all“ (Standard);
 *                mode=changes: das Jahr als Zahl (Vorjahr bis Folgejahr)
 *   row   (GET)  mode=changes: Position der Zeile in „overviewRoles“ (ab 0); -1 oder weggelassen =
 *                alle Rollen der Kachel
 *   kind  (GET)  mode=changes: „all“ (Ein- und Austritte, Standard), „joined“ (nur Eintritte) oder
 *                „left“ (nur Austritte)
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmHistory\AdmidioContext;
use AdmHistory\ChangeListRenderer;
use AdmHistory\MemberCounter;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;
use AdmHistory\OverviewTileRenderer;

$getMode = 'view';
try {
    $rootPath = dirname(__DIR__, 2);
    require_once($rootPath . '/system/common.php');
    require_once(__DIR__ . '/autoload.php');
    // globale Admidio-Variablen aus system/common.php; $page ist beim Einbinden in die
    // Übersichtsseite gesetzt (Deklaration für die statische Analyse)
    global $gValidLogin, $gCurrentUser, $page;

    $embedded = isset($page);
    $yearOptions = [
        'previous' => 'Vorjahr',
        'current'  => 'aktuelles Jahr',
        'next'     => 'Folgejahr',
    ];
    $yearKeys = array_merge(array_keys($yearOptions), ['all']);
    $currentYear = (int) date('Y');
    $yearNumbers = [
        'previous' => $currentYear - 1,
        'current'  => $currentYear,
        'next'     => $currentYear + 1,
    ];

    if (!$embedded) {
        $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', ['defaultValue' => 'view', 'validValues' => ['view', 'changes']]);
    }

    // nur angemeldete Administratoren sehen die Zahlen
    $canView = $gValidLogin && $gCurrentUser->isAdministrator();
    if (!$canView) {
        if ($embedded) {
            return; // in der Übersichtsseite: Kachel bleibt leer
        }
        throw new Exception('SYS_NO_RIGHTS');
    }

    $context = AdmidioContext::fromGlobals();
    $config = MembershipTypeConfig::fromFile(__DIR__ . '/mitgliedsarten.php');
    $repository = new MembershipRepository($context);
    $counter = new MemberCounter();
    $overviewUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/overview.php';

    // konfigurierte Zeilen in Rollen-IDs übersetzen
    $roles = $repository->findRolesByNames($config->getOverviewRoleNames());
    $overviewRows = $config->getOverviewRows();
    $rows = [];
    foreach ($overviewRows as $row) {
        $rows[$row['label']] = array_map(static fn(string $roleName): int => $roles[$roleName]->id, $row['roles']);
    }

    // ----------------------------------------------------------------------
    // Liste der Ein- und Austritte einer Zeile (eigene Seite, aus der Kachel in neuer Registerkarte)
    // ----------------------------------------------------------------------
    if ($getMode === 'changes') {
        // row = Zeile in „overviewRoles“ (ab 0); -1 oder weggelassen = alle Rollen der Kachel
        $getRow = admFuncVariableIsValid($_GET, 'row', 'int', ['defaultValue' => -1]);
        $getListYear = admFuncVariableIsValid($_GET, 'year', 'int', ['requireValue' => true]);
        $getKind = admFuncVariableIsValid($_GET, 'kind', 'string', [
            'defaultValue' => ChangeListRenderer::KIND_ALL,
            'validValues'  => ChangeListRenderer::KINDS,
        ]);
        if ($getRow !== -1 && !isset($overviewRows[$getRow])) {
            throw new RuntimeException('Die Zeile ' . $getRow . ' ist in „overviewRoles“ nicht konfiguriert.');
        }
        if (!in_array($getListYear, $yearNumbers, true)) {
            throw new RuntimeException('Die Liste gibt es nur für Vorjahr, aktuelles Jahr und Folgejahr.');
        }
        if ($getRow === -1) {
            $label = 'alle Rollen der Kachel';
            $roleNames = $config->getOverviewRoleNames();
        } else {
            $label = $overviewRows[$getRow]['label'];
            $roleNames = $overviewRows[$getRow]['roles'];
        }
        $roleIds = array_values(array_unique(array_map(static fn(string $roleName): int => $roles[$roleName]->id, $roleNames)));
        if ($roleIds === []) {
            throw new RuntimeException('Keine Rollen konfiguriert („overviewRoles“ in mitgliedsarten.php).');
        }
        $roleNameById = [];
        foreach ($roleNames as $roleName) {
            $roleNameById[$roles[$roleName]->id] = $roleName;
        }

        // Personen mit Namen und allen Mitgliedschaften in den Rollen
        $members = $repository->getMembersWithMemberships($roleIds);
        $periods = [];
        $memberByUsrId = [];
        foreach ($members as $member) {
            $memberByUsrId[$member['usr_id']] = $member;
            foreach ($member['memberships'] as $membership) {
                $periods[] = [
                    'usr_id' => $member['usr_id'],
                    'rol_id' => $membership['rol_id'],
                    'begin'  => $membership['begin'],
                    'end'    => $membership['end'],
                ];
            }
        }
        $roleIdsByUser = $counter->roleIdsInYearByUser($periods, $roleIds, $getListYear);
        $entries = [];
        foreach ($counter->changesInYear($periods, $roleIds, $getListYear) as $change) {
            // Filter nach Art: Eintritte haben ein Eintrittsdatum, Austritte ein Austrittsdatum
            if (($getKind === ChangeListRenderer::KIND_JOINED && $change['begin'] === null)
                || ($getKind === ChangeListRenderer::KIND_LEFT && $change['end'] === null)) {
                continue;
            }
            $member = $memberByUsrId[$change['usr_id']];
            $entries[] = [
                'usr_uuid'   => $member['usr_uuid'],
                'first_name' => $member['first_name'],
                'last_name'  => $member['last_name'],
                'roles'      => array_map(static fn(int $rolId): string => $roleNameById[$rolId], $roleIdsByUser[$change['usr_id']] ?? []),
                'begin'      => $change['begin'],
                'end'        => $change['end'],
            ];
        }

        $listPage = PagePresenter::withHtmlIDAndHeadline(
            'adm_history_changes',
            ChangeListRenderer::KIND_TITLES[$getKind] . ' ' . $getListYear . ': ' . $label
        );
        (new ChangeListRenderer())->render($listPage, $entries, $label, $roleNames, $getListYear, $getKind);
        $listPage->show();

        return;
    }

    // ----------------------------------------------------------------------
    // Kachel(n): Attribut „year“ der Smarty-Funktion load_admidio_plugin, sonst GET-Parameter
    // ----------------------------------------------------------------------
    $getYear = '';
    if (isset($params) && is_array($params) && isset($params['year'])) {
        $getYear = trim((string) $params['year']);
        if (!in_array($getYear, $yearKeys, true)) {
            throw new RuntimeException('Attribut year="' . $getYear . '" ist ungültig (erlaubt: ' . implode(', ', $yearKeys) . ').');
        }
    }
    if ($getYear === '') {
        $getYear = admFuncVariableIsValid($_GET, 'year', 'string', ['defaultValue' => 'all', 'validValues' => $yearKeys]);
    }

    $periods = $rows === [] ? [] : $repository->getMembershipPeriods(array_merge(...array_values($rows)));

    $allRoleIds = $rows === [] ? [] : array_values(array_unique(array_merge(...array_values($rows))));

    $years = $getYear === 'all' ? $yearNumbers : [$getYear => $yearNumbers[$getYear]];
    $tiles = [];
    foreach ($years as $key => $year) {
        $tableRows = [];
        foreach ($counter->yearTable($periods, $rows, $year) as $index => $tableRow) {
            // Link zur Liste der Ein- und Austritte nur, wenn es welche gab
            $tableRow['url'] = $tableRow['changes'] > 0
                ? SecurityUtils::encodeUrl($overviewUrl, ['mode' => 'changes', 'year' => $year, 'row' => $index])
                : '';
            $tableRows[] = $tableRow;
        }

        // Buttons „Eintritte“ und „Austritte“ über alle Rollen der Kachel
        $changes = $counter->changesInYear($periods, $allRoleIds, $year);
        $joined = count(array_filter($changes, static fn(array $change): bool => $change['begin'] !== null));
        $left = count(array_filter($changes, static fn(array $change): bool => $change['end'] !== null));

        $tiles[] = [
            'key'    => $key,
            'year'   => $year,
            'label'  => $yearOptions[$key],
            'rows'   => $tableRows,
            'joined' => [
                'count' => $joined,
                'url'   => $joined > 0 ? SecurityUtils::encodeUrl($overviewUrl, ['mode' => 'changes', 'year' => $year, 'kind' => ChangeListRenderer::KIND_JOINED]) : '',
            ],
            'left'   => [
                'count' => $left,
                'url'   => $left > 0 ? SecurityUtils::encodeUrl($overviewUrl, ['mode' => 'changes', 'year' => $year, 'kind' => ChangeListRenderer::KIND_LEFT]) : '',
            ],
        ];
    }

    $historyUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/index.php';
    $tileHtml = (new OverviewTileRenderer())->render($tiles, basename(__DIR__), $historyUrl);

    if ($embedded) {
        // in der Übersichtsseite: Admidio liefert den Kachelrahmen, hier nur der Inhalt
        echo $tileHtml;
    } else {
        $tilePage = PagePresenter::withHtmlIDAndHeadline('adm_history_overview', 'Mitglieder-Historie: Kacheln');
        $tilePage->addHtml('<div class="card admidio-card"><div class="card-body">' . $tileHtml . '</div></div>');
        $tilePage->show();
    }
} catch (Throwable $ex) {
    if ($getMode === 'changes') {
        handleException($ex);
    }
    echo '<div class="alert alert-danger" role="alert">Mitglieder-Historie: '
        . htmlspecialchars($ex->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
}
