# CLAUDE.md

Admidio-5-Plugin „Mitglieder-Historie“ (`adm_plugins/history`): Mitgliedsart je Kalenderjahr für
alle Personen, Wechsel der Mitgliedsart für aktuelles Jahr und Folgejahr. Fachliche Beschreibung,
Bedienung und Aufbau stehen in der @README.md und werden hier nicht wiederholt.

Admidio-Quellen:
https://github.com/Admidio/admidio/tree/v5.0

## Programmierrichtlinien

Grundlage sind die Admidio-Programmierrichtlinien:
https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:programmierrichtlinien


Die wichtigsten Regeln daraus, die in diesem Repo gelten:

- **Einrückung**: 4 Leerzeichen, keine Tabs (PHP, JS, HTML).
- **Klammern**: Öffnende Klammer in derselben Zeile wie `if`/`for`/`while`/`switch`, Leerzeichen
  davor. Immer Klammern setzen, auch bei einzeiligen Blöcken. `} elseif (...) {` und `} else {`
  in einer Zeile.
- **Benennung**: Klassen `PascalCase`, Funktionen und Variablen `camelCase`, Konstanten
  `GROSS_MIT_UNTERSTRICH`. Globale Admidio-Variablen tragen das Präfix `g` (`$gDb`, `$gCurrentUser`),
  Request-Parameter das Präfix `get`/`post` (`$getYears`, `$postType`). Keine kryptischen Abkürzungen.
- **Parameter** mit Standardwerten stehen am Ende der Signatur.
- **PHP-Tags**: nur `<?php`, nie `<?`. Reine PHP-Dateien ohne schließendes `?>`.
- **Strings**: einfache Anführungszeichen. Doppelte nur für HTML-Attribute innerhalb von Strings.
  In JavaScript doppelte Anführungszeichen.
- **Vergleiche**: immer `===` / `!==`, nie `==` / `!=`.
- **Kommentare**: `//` für ein bis zwei Zeilen, `/* */` ab drei Zeilen. Klassen, Methoden und
  Dateien bekommen einen Doc-Block (Doxygen/PHPDoc-Stil) mit Zweck und Parametern.
- **Dateien**: Kleinbuchstaben mit Unterstrichen (`mitgliedsarten.php`), UTF-8 ohne BOM, LF-Zeilenenden.
  Ausnahme: Klassendateien in `classes/` heißen wie die Klasse (`YearHistory.php`), damit der
  Autoloader in `index.php` sie findet.
- **Datenbank**: nur parametrisierte Abfragen über die Admidio-Datenbankklasse (`$gDb`, Platzhalter
  `?` mit Parameter-Array). Nie Werte in SQL-Strings einbauen. Tabellennamen über die
  Konstanten `TBL_*`.
- **Eingaben**: alle GET/POST-Parameter über `admFuncVariableIsValid()` prüfen. POST-Formulare mit
  CSRF-Token (`SecurityUtils::validateCsrfToken()`). Ausgaben mit `htmlspecialchars` escapen.
- **Commits**: kurze Beschreibung der Änderung, zugehörige Issue-Nummer angeben (z. B. `#12`),
  falls vorhanden.

## Abweichungen und Ergänzungen für dieses Repo

- **Sprache**: Kommentare, Doc-Blöcke, README und Anzeigetexte sind auf Deutsch (abweichend von
  der Admidio-Richtlinie, die Englisch vorsieht). Bezeichner im Code bleiben Englisch.
- **PHP 8.4+**: `declare`-freie, moderne Syntax ist erwünscht: `readonly`-Properties,
  Constructor Promotion, benannte Argumente, `static fn`, `str_starts_with` usw.
- **Namespace**: alle Klassen liegen unter `AdmHistory\` in `classes/`, eine Klasse pro Datei,
  alle Klassen sind `final`.
- **Konfiguration**: `mitgliedsarten.php` ist die einzige Stelle, an der Mitgliedsarten, deren
  Rollen, Farben und gemeinsame Rollen stehen. Sie ist zugleich das dokumentierte Beispiel (README
  und Doc-Blöcke verweisen nur darauf). Gelesen und geprüft wird sie ausschließlich über
  `MembershipTypeConfig`; Prüfungen der Konfiguration gehören dorthin, nicht in den Controller.
- **Admidio-API**: Klassen aus dem Admidio-Kern per `use Admidio\...` einbinden, keine Kopien.
  Globale Admidio-Variablen (`$gDb`, `$gCurrentUser`, ...) werden nur in `index.php` und
  `AdmidioContext::fromGlobals()` gelesen; alle anderen Klassen bekommen den `AdmidioContext`
  übergeben. SQL gehört ausschließlich in `MembershipRepository`; Rollen werden als `RoleRef`
  weitergereicht. Mitgliedschaften werden nur über die Admidio-Entity `Membership` geschrieben
  (`MembershipChanger`), nie per direktem SQL, damit Änderungsprotokoll und Benachrichtigungen
  erhalten bleiben.
- **Logik ohne Admidio**: Was sich ohne Datenbank berechnen lässt (Jahreslogik in `YearHistory`,
  Änderungsplanung in `MembershipChangePlanner`, Konfiguration), bleibt frei von Admidio-Klassen
  und wird mit PHPUnit getestet. Admidio-gebundene Klassen (`MembershipRepository`,
  `MembershipChanger`, `HistoryTableRenderer`) bleiben dünn und werden im laufenden Admidio geprüft.
- **Wechsel-Semantik**: Ein Wechsel gilt immer ab dem 1. Januar des gewählten Jahres (nur aktuelles
  Jahr und Folgejahr); die Jahresspalte zeigt die Mitgliedsart am 31.12. Änderungen an dieser
  Semantik werden in README, `mitgliedsarten.php` und `MembershipChangePlanner` gleichzeitig
  nachgezogen.
- **Tests, Lint, PHPStan**: `composer check` (Lint, PHPUnit, PHPStan) vor jedem Commit ausführen.
  Neue Admidio-Klassen oder -Methoden, die das Plugin verwendet, werden in `stubs/admidio.php`
  als Signatur nachgetragen, damit PHPStan ohne Admidio läuft (der Meta-Workflow in
  `.github/workflows/meta.yml` führt PHPStan und php-cs-fixer aus).
- **README pflegen**: neue Klassen oder neue Konfigurationsschlüssel werden in der README
  (Tabelle „Mitgliedsarten konfigurieren“ und Liste in „Aufbau“) nachgetragen. Keine
  Code-Beispiele in die README kopieren.
- **Nicht ins Repo**: `config.php`, `vendor/`, IDE-Ordner (siehe `.gitignore`).

