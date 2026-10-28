<?php
/**
 * Admidio-Plugin: Mitglieder-Historie – Schnellwechsel
 *
 * Zeigt alle Personen, die im aktuellen Jahr oder im Folgejahr Mitglied sind, mit je einer Spalte
 * für beide Jahre. Jede Spalte enthält einen Button je Mitgliedsart, einen Button „Kündigung“ und
 * die optionalen Beitragsrollen (Zusatzbeiträge) der gewählten Mitgliedsart zum Ein- und
 * Ausschalten. Gespeichert wird über AdmHistory\MembershipApi::change(), hier mit Rechteprüfung
 * und CSRF-Token, weil die Anfrage aus der Seite kommt.
 *
 * Parameter:
 *   mode       (GET)  „save“ zum Speichern (POST per fetch, JSON im Request-Body, Antwort als
 *                     JSON), sonst Übersicht
 *   user_uuid  (JSON) Person, deren Mitgliedschaft geändert wird
 *   year       (JSON) Jahr, ab dessen 1. Januar die Änderung gilt (aktuelles Jahr oder Folgejahr)
 *   art        (JSON) Kürzel der neuen Mitgliedsart, '' = kein Mitglied (Kündigung)
 *   fee        (JSON) gewünschte optionale Beitragsrollen für das Jahr
 *   adm_csrf_token (JSON) CSRF-Token der Session
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmHistory\AdmidioContext;
use AdmHistory\HistoryLoader;
use AdmHistory\MembershipApi;
use AdmHistory\MembershipChanger;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;
use AdmHistory\QuickChangeRenderer;
use AdmHistory\YearHistory;

/**
 * Sendet eine JSON-Antwort und beendet das Skript (Modus „save“).
 * @param array<string, mixed> $data
 */
function admHistoryQuickSendJson(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit();
}

try {
    $rootPath = dirname(__DIR__, 2);
    require_once($rootPath . '/system/common.php');
    // globale Admidio-Variablen aus system/common.php (Deklaration für die statische Analyse)
    global $gValidLogin, $gCurrentUser, $gNavigation, $gCurrentSession;

    require_once(__DIR__ . '/autoload.php');

    // Die Seite dient nur zum Ändern: Rechte wie beim Wechsel in index.php
    $canEdit = $gValidLogin && ($gCurrentUser->isAdministrator() || $gCurrentUser->checkRolesRight('rol_assign_roles'));
    if (!$canEdit) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', ['defaultValue' => 'view', 'validValues' => ['view', 'save']]);

    $context = AdmidioContext::fromGlobals();
    $pluginFolderUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__);
    $pageUrl = $pluginFolderUrl . '/quick_change.php';
    $config = MembershipTypeConfig::fromFile(__DIR__ . '/mitgliedsarten.php');
    $loader = new HistoryLoader(new MembershipRepository($context), $config);
    $history = new YearHistory($config);

    $currentYear = (int) date('Y');
    $editableYears = [$currentYear, $currentYear + 1];

    // ----------------------------------------------------------------------
    // Speichern über MembershipApi (POST per fetch, JSON im Request-Body, Antwort als JSON)
    // ----------------------------------------------------------------------
    if ($getMode === 'save') {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Änderungen sind nur per POST möglich.');
            }
            $input = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input)) {
                throw new RuntimeException('Ungültige Eingabe: JSON-Objekt mit user_uuid, year, art, fee erwartet.');
            }
            SecurityUtils::validateCsrfToken((string) ($input['adm_csrf_token'] ?? ''));

            $postUserUuid = admFuncVariableIsValid($input, 'user_uuid', 'uuid', ['requireValue' => true]);
            $postYear = admFuncVariableIsValid($input, 'year', 'int', ['requireValue' => true]);
            $postArt = admFuncVariableIsValid($input, 'art', 'string');
            $postFee = $input['fee'] ?? [];
            if (!is_array($postFee)) {
                throw new RuntimeException('„fee“ muss ein Array von Beitragsrollen sein.');
            }

            $api = new MembershipApi($config, $loader, $history, new MembershipChanger($context, $config, $loader, $history));
            $result = $api->change($postUserUuid, $postYear, $postArt, array_map(strval(...), $postFee));

            // neuen Stand beider Jahre zurückgeben: ein Wechsel im aktuellen Jahr wirkt auch im Folgejahr
            $user = $loader->loadUser($postUserUuid);
            if ($user === null) {
                throw new RuntimeException('Die Person wurde nicht gefunden.');
            }
            $years = [];
            foreach ($editableYears as $year) {
                $years[(string) $year] = $history->yearStateWithFees($user['periods'], $user['commonPeriods'], $user['feePeriods'], $year);
            }
            admHistoryQuickSendJson(['status' => 'ok', ...$result, 'years' => $years]);
        } catch (Throwable $ex) {
            admHistoryQuickSendJson(['status' => 'error', 'message' => $ex->getMessage()]);
        }
    }

    // ----------------------------------------------------------------------
    // Übersicht: alle Mitglieder des aktuellen Jahres und des Folgejahres
    // ----------------------------------------------------------------------
    $headline = 'Schnellwechsel Mitgliedsart';
    $gNavigation->addStartUrl($pageUrl, $headline, 'bi-lightning');

    $page = PagePresenter::withHtmlIDAndHeadline('adm_history_quick', $headline);
    $page->setContentFullWidth();
    $page->addPageFunctionsMenuItem('adm_history_menu_history', 'Mitglieder-Historie', $pluginFolderUrl . '/index.php', 'bi-clock-history');

    $users = array_filter(
        $loader->loadAll(),
        static fn(array $user): bool => $history->isMemberInYear($user['periods'], $user['commonPeriods'], $currentYear)
            || $history->isMemberInYear($user['periods'], $user['commonPeriods'], $currentYear + 1)
    );

    $renderer = new QuickChangeRenderer($context, $config, $history);
    $renderer->render($page, $users, $editableYears, $pageUrl . '?mode=save', $gCurrentSession->getCsrfToken());

    $page->show();
} catch (Throwable $ex) {
    handleException($ex);
}
