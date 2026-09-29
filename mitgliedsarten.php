<?php
/**
 * Konfiguration der Mitgliedsarten für das Plugin „Mitglieder-Historie“.
 *
 * Jede Mitgliedsart hat ein Kürzel (Schlüssel des Arrays, wird in den Jahresspalten angezeigt),
 * einen Namen, eine Hintergrundfarbe und die Admidio-Rollen, die zu ihr gehören. Eine Person hat
 * die Mitgliedsart in einem Jahr, wenn sie in diesem Jahr in mindestens einer der Rollen war.
 *
 * Beim Wechsel über die Auswahl (aktuelles Jahr und Folgejahr) gilt ab dem 1. Januar des
 * gewählten Jahres:
 *   - alle Rollen der neuen Mitgliedsart werden begonnen (falls noch nicht aktiv),
 *   - alle Rollen der übrigen Mitgliedsarten werden am 31. Dezember des Vorjahres beendet,
 *   - die gemeinsamen Rollen (commonRoles) bleiben bzw. werden begonnen; bei „kein Mitglied“
 *     werden sie ebenfalls beendet,
 *   - die Pflicht-Beitragsrollen (mandatoryFeeRoles) der neuen Mitgliedsart werden begonnen,
 *     optionale Beitragsrollen (optionalFeeRoles) der neuen Mitgliedsart bleiben, alle übrigen
 *     Beitragsrollen werden beendet.
 *
 * Die Reihenfolge der Mitgliedsarten bestimmt die Reihenfolge in der Auswahl und in der Legende.
 * Eine Mitgliedsart kann mehrere Rollen umfassen, z. B. 'roles' => ['Aktiv', 'Bootsbeitrag'].
 * Jede Rolle darf nur zu einer Mitgliedsart gehören.
 */

return [
    'types' => [
        'A' => ['name' => 'Aktiv',  'color' => '#cfe2ff', 'roles' => ['Aktiv']],
        'E' => ['name' => 'Ehren',  'color' => '#fff3cd', 'roles' => ['Ehren']],
        'F' => ['name' => 'Förder', 'color' => '#e2d9f3', 'roles' => ['Förder']],
        'J' => ['name' => 'Jugend', 'color' => '#d1e7dd', 'roles' => ['Jugend']],
        'P' => ['name' => 'Passiv', 'color' => '#f8d7da', 'roles' => ['Passiv']],
    ],

    // Rollen, in denen jedes Mitglied unabhängig von der Mitgliedsart ist (übergeordnete Rolle).
    // Sie erhalten kein eigenes Kürzel; ein Jahr, in dem jemand nur in einer dieser Rollen war
    // (Mitgliedsart nicht ermittelbar), zeigt „?“. Beim Wechsel werden sie mitgeführt.
    'commonRoles' => ['Mitglied'],

    // Erlaubte Wechsel in der Auswahl: bisheriges Kürzel => erlaubte neue Kürzel.
    // '' steht für „kein Mitglied“ (als Schlüssel: Eintritt, als Ziel: Austritt), '*' für alle Ziele.
    // Beibehalten ist immer erlaubt. Wird der Schlüssel „transitions“ ganz weggelassen, ist jeder
    // Wechsel erlaubt; ein hier nicht genanntes bisheriges Kürzel erlaubt keinen Wechsel.
    'transitions' => [
        ''  => ['A', 'E', 'F', 'J', 'P'], // Eintritt
        'A' => ['P', '', 'F', 'E'],
        'E' => [''],
        'F' => ['A', 'P', ''],
        'J' => ['A', 'F', 'P', ''],
        'P' => ['A', 'F', '', 'E'],
    ],

    // Pflicht-Beitragsrollen je Mitgliedsart: beim Wechsel zu der Mitgliedsart werden sie ab dem
    // 1. Januar begonnen, beim Wechsel weg von ihr (oder beim Austritt) am 31. Dezember des
    // Vorjahres beendet. Mitgliedsarten ohne Pflicht-Beitragsrolle können weggelassen werden.
    'mandatoryFeeRoles' => [
        'A' => ['Aktivbeitrag'],
        'J' => ['Jugendbeitrag'],
        'P' => ['Passivbeitrag'],
    ],

    // Optionale Beitragsrollen (Zusatzbeiträge) je Mitgliedsart: sie werden beim Wechsel nicht
    // begonnen, bleiben aber bestehen, solange sie zur neuen Mitgliedsart gehören; sonst enden sie
    // am 31. Dezember des Vorjahres. Für das aktuelle Jahr und das Folgejahr werden sie in der
    // Spalte „Zusatz“ angezeigt. Eine Rolle darf bei mehreren Mitgliedsarten stehen.
    'optionalFeeRoles' => [
        'A' => ['Bearbeitungsgebühr', 'Bootsbeitrag', 'Erstbesatz', 'passiv->aktiv Differenz'],
        'F' => ['Förderbeitrag 100€', 'Förderbeitrag 50€', 'Förderbeitrag 25€', 'Förderbeitrag 20€', 'Förderbeitrag 12€'],
        'J' => ['2.Angel'],
    ],

    // Anzahl der Jahre vor dem aktuellen Jahr, die standardmäßig angezeigt werden (0 = alle).
    // In der Übersicht kann der Zeitraum jederzeit umgestellt werden.
    'historyYears' => 25,
];
