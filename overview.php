<?php
/**
 * Admidio-Plugin: Mitglieder-Historie – Kacheln für die Admidio-Übersichtsseite
 *
 * Zeigt je Jahr (Vorjahr, aktuelles Jahr, Folgejahr) eine kleine Tabelle, wie viele Personen am
 * 1. Januar und am 31. Dezember in den konfigurierten Rollen („overviewRoles“ in mitgliedsarten.php)
 * waren. Gab es im Jahr Ein- oder Austritte, ist der Rollenname ein Link, der in einer neuen
 * Registerkarte die Liste dieser Personen öffnet (mode=changes). Eingebunden wird die Datei in der
 * Vorlage system/overview.tpl des Themes:
 *
 *   {load_admidio_plugin plugin="history" file="overview.php" year="next"}
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
 *   row   (GET)  mode=changes: Position der Zeile in „overviewRoles“ (ab 0)
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmHistory\AdmidioContext;
use AdmHistory\ChangeListRenderer;
use AdmHistory\MemberCounter;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;

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
        $getRow = admFuncVariableIsValid($_GET, 'row', 'int', ['requireValue' => true]);
        $getListYear = admFuncVariableIsValid($_GET, 'year', 'int', ['requireValue' => true]);
        if (!isset($overviewRows[$getRow])) {
            throw new RuntimeException('Die Zeile ' . $getRow . ' ist in „overviewRoles“ nicht konfiguriert.');
        }
        if (!in_array($getListYear, $yearNumbers, true)) {
            throw new RuntimeException('Die Liste gibt es nur für Vorjahr, aktuelles Jahr und Folgejahr.');
        }
        $label = $overviewRows[$getRow]['label'];
        $roleIds = $rows[$label];

        // Personen mit Namen und allen Mitgliedschaften in den Rollen der Zeile
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
        $entries = [];
        foreach ($counter->changesInYear($periods, $roleIds, $getListYear) as $change) {
            $member = $memberByUsrId[$change['usr_id']];
            $entries[] = [
                'usr_uuid'   => $member['usr_uuid'],
                'first_name' => $member['first_name'],
                'last_name'  => $member['last_name'],
                'begin'      => $change['begin'],
                'end'        => $change['end'],
            ];
        }

        $listPage = PagePresenter::withHtmlIDAndHeadline('adm_history_changes', 'Ein- und Austritte ' . $getListYear . ': ' . $label);
        (new ChangeListRenderer())->render($listPage, $entries, $label, $overviewRows[$getRow]['roles'], $getListYear);
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
        $tiles[] = [
            'key'   => $key,
            'year'  => (string) $year,
            'label' => $yearOptions[$key],
            'rows'  => $tableRows,
        ];
    }

    $overviewPlugin = new Overview(basename(__DIR__));
    $overviewPlugin->assignTemplateVariable('tiles', $tiles);
    $overviewPlugin->assignTemplateVariable('historyUrl', ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/index.php');

    if ($embedded) {
        echo $overviewPlugin->html('plugin.history-overview.tpl');
    } else {
        $overviewPlugin->showHtmlPage('plugin.history-overview.tpl');
    }
} catch (Throwable $ex) {
    if ($getMode === 'changes') {
        handleException($ex);
    }
    echo '<div class="alert alert-danger" role="alert">Mitglieder-Historie: '
        . htmlspecialchars($ex->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
}
