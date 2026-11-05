<?php
/**
 * Admidio-Plugin: Mitglieder-Historie
 *
 * Zeigt alle Personen mit ihrer Mitgliedsart je Kalenderjahr (Aktiv, Ehren, Förder, Jugend,
 * Passiv, ... laut mitgliedsarten.php) als Tabelle: Vorname, Nachname, Folgejahr, aktuelles Jahr,
 * Vorjahr, ... Die Seite ist eine reine Ansicht; geändert wird die Mitgliedsart im Schnellwechsel
 * (quick_change.php).
 *
 * Parameter:
 *   status     (GET)  Filter nach aktueller Mitgliedschaft: „active“ (heute Mitglied, Standard),
 *                     „former“ (früher Mitglied, heute nicht mehr) oder „all“
 *   years      (GET)  Anzahl der Jahre vor dem aktuellen Jahr (0 = alle); Standard aus mitgliedsarten.php
 *   duration   (GET)  Spalte „Ununterbrochen seit“: „all“ (Mitglied in irgendeiner Mitgliedsart, Standard)
 *                     oder Kürzel einer Mitgliedsart (z. B. „A“)
 */

use Admidio\Infrastructure\Exception;
use Admidio\UI\Presenter\PagePresenter;
use AdmHistory\AdmidioContext;
use AdmHistory\HistoryLoader;
use AdmHistory\HistoryTableRenderer;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;
use AdmHistory\YearHistory;

try {
    $rootPath = dirname(__DIR__, 2);
    require_once($rootPath . '/system/common.php');
    // globale Admidio-Variablen aus system/common.php (Deklaration für die statische Analyse)
    global $gValidLogin, $gCurrentUser, $gNavigation;

    require_once(__DIR__ . '/autoload.php');

    $canEdit = $gValidLogin && ($gCurrentUser->isAdministrator() || $gCurrentUser->checkRolesRight('rol_assign_roles'));
    $canView = $canEdit || ($gValidLogin && $gCurrentUser->checkRolesRight('rol_edit_user'));
    if (!$canView) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $context = AdmidioContext::fromGlobals();
    $pluginUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/index.php';
    $config = MembershipTypeConfig::fromFile(__DIR__ . '/mitgliedsarten.php');
    $loader = new HistoryLoader(new MembershipRepository($context), $config);
    $history = new YearHistory($config);

    $currentYear = (int) date('Y');

    // ----------------------------------------------------------------------
    // Übersicht: Tabelle aller Personen mit Mitgliedsart je Jahr
    // ----------------------------------------------------------------------
    $getYears = admFuncVariableIsValid($_GET, 'years', 'int', ['defaultValue' => $config->getHistoryYears()]);
    if ($getYears < 0) {
        $getYears = 0;
    }
    $getStatus = admFuncVariableIsValid($_GET, 'status', 'string', [
        'defaultValue' => HistoryTableRenderer::STATUS_ACTIVE,
        'validValues'  => HistoryTableRenderer::STATUS_OPTIONS,
    ]);

    $getDuration = admFuncVariableIsValid($_GET, 'duration', 'string', [
        'defaultValue' => HistoryTableRenderer::DURATION_ALL,
        'validValues'  => array_merge([HistoryTableRenderer::DURATION_ALL], array_keys($config->getTypes())),
    ]);

    $headline = 'Mitglieder-Historie';
    $gNavigation->addStartUrl($pluginUrl, $headline, 'bi-clock-history');

    $page = PagePresenter::withHtmlIDAndHeadline('adm_history', $headline);
    $page->setContentFullWidth();
    if ($canEdit) {
        $page->addPageFunctionsMenuItem('adm_history_menu_quick', 'Schnellwechsel', ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/quick_change.php', 'bi-lightning');
    }

    $users = $loader->loadAll();

    // Filter nach aktueller Mitgliedschaft (Stichtag heute)
    if ($getStatus !== HistoryTableRenderer::STATUS_ALL) {
        $today = date('Y-m-d');
        $wantActive = $getStatus === HistoryTableRenderer::STATUS_ACTIVE;
        $users = array_filter(
            $users,
            static fn(array $user): bool => $history->isMemberAtDate($user['periods'], $user['commonPeriods'], $today) === $wantActive
        );
    }

    // „alle Jahre“: ab der ältesten Mitgliedschaft, auch in den gemeinsamen Rollen
    $allPeriods = array_map(static fn(array $user): array => array_merge($user['periods'], $user['commonPeriods']), $users);
    $firstYear = $getYears === 0 ? ($history->firstYear($allPeriods) ?? $currentYear) : $currentYear - $getYears;
    $firstYear = min($firstYear, $currentYear);
    $years = range($currentYear + 1, $firstYear); // absteigend

    $renderer = new HistoryTableRenderer($context, $config, $history);
    $renderer->renderToolbar($page, $pluginUrl, $getYears, $getStatus, $getDuration);
    $renderer->render($page, $users, $years, $getDuration);

    $page->show();
} catch (Throwable $ex) {
    handleException($ex);
}
