<?php
/**
 * Admidio-Plugin: Mitglieder-Historie – Kacheln für die Admidio-Übersichtsseite
 *
 * Zeigt je Jahr (Vorjahr, aktuelles Jahr, Folgejahr) eine kleine Tabelle, wie viele Personen am
 * 1. Januar und am 31. Dezember in den konfigurierten Rollen („overviewRoles“ in mitgliedsarten.php)
 * waren. Eingebunden wird die Datei in der Vorlage system/overview.tpl des Themes:
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
 *   year  (Smarty-Attribut oder GET)  „previous“, „current“, „next“ oder „all“ (Standard)
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Overview;
use AdmHistory\AdmidioContext;
use AdmHistory\MemberCounter;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;

try {
    $rootPath = dirname(__DIR__, 2);
    require_once($rootPath . '/system/common.php');
    require_once(__DIR__ . '/autoload.php');
    // globale Admidio-Variablen aus system/common.php; $page ist beim Einbinden in die
    // Übersichtsseite gesetzt (Deklaration für die statische Analyse)
    global $gValidLogin, $gCurrentUser, $page;

    $yearOptions = [
        'previous' => 'Vorjahr',
        'current'  => 'aktuelles Jahr',
        'next'     => 'Folgejahr',
    ];
    $yearKeys = array_merge(array_keys($yearOptions), ['all']);

    // Attribut „year“ der Smarty-Funktion load_admidio_plugin, sonst GET-Parameter
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

    // nur angemeldete Administratoren sehen die Zahlen
    $canView = $gValidLogin && $gCurrentUser->isAdministrator();
    if (!$canView) {
        if (isset($page)) {
            return; // in der Übersichtsseite: Kachel bleibt leer
        }
        throw new Exception('SYS_NO_RIGHTS');
    }

    $context = AdmidioContext::fromGlobals();
    $config = MembershipTypeConfig::fromFile(__DIR__ . '/mitgliedsarten.php');
    $repository = new MembershipRepository($context);
    $counter = new MemberCounter();

    // konfigurierte Zeilen in Rollen-IDs übersetzen und alle Mitgliedschaften dieser Rollen laden
    $roles = $repository->findRolesByNames($config->getOverviewRoleNames());
    $rows = [];
    foreach ($config->getOverviewRows() as $row) {
        $rows[$row['label']] = array_map(static fn(string $roleName): int => $roles[$roleName]->id, $row['roles']);
    }
    $periods = $rows === [] ? [] : $repository->getMembershipPeriods(array_merge(...array_values($rows)));

    $currentYear = (int) date('Y');
    $years = [
        'previous' => $currentYear - 1,
        'current'  => $currentYear,
        'next'     => $currentYear + 1,
    ];
    if ($getYear !== 'all') {
        $years = [$getYear => $years[$getYear]];
    }

    $tiles = [];
    foreach ($years as $key => $year) {
        $tiles[] = [
            'key'   => $key,
            'year'  => (string) $year,
            'label' => $yearOptions[$key],
            'rows'  => $counter->yearTable($periods, $rows, $year),
        ];
    }

    $overviewPlugin = new Overview(basename(__DIR__));
    $overviewPlugin->assignTemplateVariable('tiles', $tiles);
    $overviewPlugin->assignTemplateVariable('historyUrl', ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/index.php');

    if (isset($page)) {
        echo $overviewPlugin->html('plugin.history-overview.tpl');
    } else {
        $overviewPlugin->showHtmlPage('plugin.history-overview.tpl');
    }
} catch (Throwable $ex) {
    echo '<div class="alert alert-danger" role="alert">Mitglieder-Historie: '
        . htmlspecialchars($ex->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
}
