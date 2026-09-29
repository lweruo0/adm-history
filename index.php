<?php
/**
 * Admidio-Plugin: Mitglieder-Historie
 *
 * Zeigt alle Personen mit ihrer Mitgliedsart je Kalenderjahr (Aktiv, Ehren, Förder, Jugend,
 * Passiv, ... laut mitgliedsarten.php) als Tabelle: Vorname, Nachname, Folgejahr, aktuelles Jahr,
 * Vorjahr, ... Für das aktuelle Jahr und das Folgejahr kann die Mitgliedsart über eine Auswahl
 * geändert werden; der Wechsel gilt ab dem 1. Januar des jeweiligen Jahres.
 *
 * Parameter:
 *   status     (GET)  Filter nach aktueller Mitgliedschaft: „active“ (heute Mitglied, Standard),
 *                     „former“ (früher Mitglied, heute nicht mehr) oder „all“
 *   years      (GET)  Anzahl der Jahre vor dem aktuellen Jahr (0 = alle); Standard aus mitgliedsarten.php
 *   mode       (GET)  „change“ für die Änderung der Mitgliedsart, „addfee“ für einen Zusatzbeitrag
 *                     (beide per fetch(), POST, Antwort als JSON), sonst Übersicht
 *   user_uuid  (POST) Person, deren Mitgliedschaft geändert wird
 *   year       (POST) Jahr, ab dessen 1. Januar die Änderung gilt (aktuelles Jahr oder Folgejahr)
 *   type       (POST) mode=change: Kürzel der neuen Mitgliedsart, leer = kein Mitglied
 *   role       (POST) mode=addfee: konfigurierter Name der optionalen Beitragsrolle
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmHistory\AdmidioContext;
use AdmHistory\HistoryLoader;
use AdmHistory\HistoryTableRenderer;
use AdmHistory\MembershipChanger;
use AdmHistory\MembershipRepository;
use AdmHistory\MembershipTypeConfig;
use AdmHistory\YearHistory;

/**
 * Sendet eine JSON-Antwort und beendet das Skript (Modus „change“).
 * @param array<string, mixed> $data
 */
function admHistorySendJson(array $data): never
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

    spl_autoload_register(static function (string $class): void {
        $prefix = 'AdmHistory\\';
        if (str_starts_with($class, $prefix)) {
            $file = __DIR__ . '/classes/' . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    });

    $canEdit = $gValidLogin && ($gCurrentUser->isAdministrator() || $gCurrentUser->checkRolesRight('rol_assign_roles'));
    $canView = $canEdit || ($gValidLogin && $gCurrentUser->checkRolesRight('rol_edit_user'));
    if (!$canView) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', ['defaultValue' => 'view', 'validValues' => ['view', 'change', 'addfee']]);

    $context = AdmidioContext::fromGlobals();
    $pluginUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/' . basename(__DIR__) . '/index.php';
    $config = MembershipTypeConfig::fromFile(__DIR__ . '/mitgliedsarten.php');
    $loader = new HistoryLoader(new MembershipRepository($context), $config);
    $history = new YearHistory($config);

    $currentYear = (int) date('Y');
    $editableYears = [$currentYear, $currentYear + 1];

    // ----------------------------------------------------------------------
    // Änderung der Mitgliedsart oder Zusatzbeitrag hinzufügen (POST per fetch, Antwort als JSON)
    // ----------------------------------------------------------------------
    if ($getMode !== 'view') {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Änderungen sind nur per POST möglich.');
            }
            if (!$canEdit) {
                throw new RuntimeException('Keine Berechtigung, Mitgliedsarten zu ändern.');
            }
            SecurityUtils::validateCsrfToken((string) ($_POST['adm_csrf_token'] ?? ''));

            $postUserUuid = admFuncVariableIsValid($_POST, 'user_uuid', 'uuid', ['requireValue' => true]);
            $postYear = admFuncVariableIsValid($_POST, 'year', 'int', ['requireValue' => true]);

            if (!in_array($postYear, $editableYears, true)) {
                throw new RuntimeException('Änderungen sind nur für das aktuelle Jahr und das Folgejahr möglich.');
            }
            $user = $loader->loadUser($postUserUuid);
            if ($user === null) {
                throw new RuntimeException('Die Person wurde nicht gefunden.');
            }
            $changer = new MembershipChanger($context, $config, $loader, $history);

            if ($getMode === 'addfee') {
                $postRole = admFuncVariableIsValid($_POST, 'role', 'string', ['requireValue' => true]);
                $operations = $changer->addFee($user, $postYear, $postRole);
                $message = $operations === 0 ? 'Der Zusatzbeitrag ist bereits vorhanden.' : 'Zusatzbeitrag gespeichert.';
            } else {
                $postType = admFuncVariableIsValid($_POST, 'type', 'string');
                $newType = null;
                if ($postType !== '') {
                    $newType = $config->getType($postType);
                    if ($newType === null) {
                        throw new RuntimeException('Unbekannte Mitgliedsart „' . $postType . '“.');
                    }
                }
                $operations = $changer->change($user, $postYear, $newType);
                $message = $operations === 0 ? 'Keine Änderung notwendig.' : 'Mitgliedsart gespeichert.';
            }

            // neuen Stand der änderbaren Jahre zurückgeben, damit die Anzeige ohne Neuladen stimmt
            $user = $loader->loadUser($postUserUuid) ?? $user;
            $years = [];
            $fees = [];
            foreach ($editableYears as $year) {
                $years[(string) $year] = $history->yearState($user['periods'], $year, $user['commonPeriods']);
                $fees[(string) $year] = $history->rolesInYear($user['feePeriods'], $year);
            }
            admHistorySendJson([
                'status'  => 'ok',
                'message' => $message,
                'years'   => $years,
                'fees'    => $fees,
            ]);
        } catch (Throwable $ex) {
            admHistorySendJson(['status' => 'error', 'message' => $ex->getMessage()]);
        }
    }

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

    $headline = 'Mitglieder-Historie';
    $gNavigation->addStartUrl($pluginUrl, $headline, 'bi-clock-history');

    $page = PagePresenter::withHtmlIDAndHeadline('adm_history', $headline);
    $page->setContentFullWidth();

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
    $renderer->renderToolbar($page, $pluginUrl, $getYears, $getStatus);
    if (!$canEdit) {
        $page->addHtml('<div class="alert alert-secondary" role="alert"><i class="bi bi-eye"></i> Nur Ansicht: Zum Ändern der Mitgliedsart ist das Recht „Rollen zuordnen“ nötig.</div>');
    }
    $renderer->render(
        $page,
        $users,
        $years,
        $editableYears,
        $canEdit,
        $pluginUrl,
        $gCurrentSession->getCsrfToken()
    );

    $page->show();
} catch (Throwable $ex) {
    handleException($ex);
}
