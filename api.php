<?php
/**
 * Admidio-Plugin: Mitglieder-Historie – schlanke Programmierschnittstelle
 *
 * Für externe Aufrufer (z. B. eine eigene REST-API), die Authentifizierung und Zugriffsschutz
 * bereits selbst erledigen: setzt Mitgliedsart und optionale Beitragsrollen (Zusatzbeiträge)
 * einer Person ab dem 1. Januar eines Jahres. Kein CSRF-Token, keine Rechteprüfung auf
 * Oberflächenebene; die Rollen-Rechte des angemeldeten Admidio-Benutzers prüft MembershipChanger
 * weiterhin. Die fachliche Logik steht in AdmHistory\MembershipApi, die sich auch direkt aus PHP
 * instanzieren lässt (AdmHistory\MembershipApi::fromContext()).
 *
 * Eingaben als JSON im Request-Body:
 *   user_uuid (string) Person, deren Mitgliedschaft geändert wird
 *   year      (int)    Jahr, ab dessen 1. Januar die Änderung gilt (aktuelles Jahr oder Folgejahr)
 *   art       (string) Kürzel oder Name der neuen Mitgliedsart (Groß-/Kleinschreibung egal), '' = kein Mitglied
 *   fee       (array)  gewünschte optionale Beitragsrollen für das Jahr (konfigurierter Name, Groß-/Kleinschreibung egal)
 *
 * Antwort als JSON: {"status":"ok","type":"A","fees":[...],"operations":2}
 *                 oder {"status":"error","message":"..."}
 */

use AdmHistory\AdmidioContext;
use AdmHistory\MembershipApi;

/**
 * Sendet eine JSON-Antwort und beendet das Skript.
 * @param array<string, mixed> $data
 */
function admHistoryApiSendJson(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit();
}

try {
    $rootPath = dirname(__DIR__, 2);
    require_once($rootPath . '/system/common.php');

    require_once(__DIR__ . '/autoload.php');

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new RuntimeException('Ungültige Eingabe: JSON-Objekt mit user_uuid, year, art, fee erwartet.');
    }

    $userUuid = admFuncVariableIsValid($input, 'user_uuid', 'uuid', ['requireValue' => true]);
    $year = admFuncVariableIsValid($input, 'year', 'int', ['requireValue' => true]);
    $art = admFuncVariableIsValid($input, 'art', 'string');
    $fee = $input['fee'] ?? [];
    if (!is_array($fee)) {
        throw new RuntimeException('„fee“ muss ein Array von Beitragsrollen sein.');
    }

    $api = MembershipApi::fromContext(AdmidioContext::fromGlobals());
    $result = $api->change($userUuid, $year, $art, array_map(strval(...), $fee));

    admHistoryApiSendJson(['status' => 'ok', ...$result]);
} catch (Throwable $ex) {
    admHistoryApiSendJson(['status' => 'error', 'message' => $ex->getMessage()]);
}
