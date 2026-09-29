# Mitglieder-Historie (Admidio 5)

Admidio-Plugin, das für alle Personen die Mitgliedsart je Kalenderjahr als Tabelle zeigt:
Vorname, Nachname, Folgejahr, aktuelles Jahr, Vorjahr, ... In jeder Jahresspalte stehen die
Kürzel der Mitgliedsarten (z. B. `A`, `E`, `F`, `J`, `P` für „Aktiv“, „Ehren“, „Förder“, „Jugend“
und „Passiv“) mit eigener Hintergrundfarbe. Für das aktuelle Jahr und das Folgejahr bietet jede
Zeile eine Auswahl, mit der die Mitgliedsart ab dem 1. Januar des jeweiligen Jahres gewechselt
werden kann.

Welche Admidio-Rollen zu welcher Mitgliedsart gehören, steht in `mitgliedsarten.php` und lässt sich
dort ohne Programmierung ändern oder erweitern.

## Installation

1. Ordner nach `adm_plugins/history` kopieren (der Ordnername ist frei wählbar).
2. `mitgliedsarten.php` an die eigenen Rollen anpassen (siehe unten).
3. `adm_plugins/history/index.php` als Administrator aufrufen oder wie unten beschrieben im
   Admidio-Menü verlinken.

Ansehen dürfen Administratoren und Benutzer mit dem Recht „alle Profile bearbeiten“ oder „Rollen
zuordnen“. Ändern dürfen Administratoren und Benutzer mit dem Recht „Rollen zuordnen“; zusätzlich
prüft Admidio je Rolle, ob der Benutzer ihr Mitglieder zuordnen darf.

### Menüpunkt in Admidio anlegen

1. Als Administrator anmelden und in der Navigation **Administration → Menü** öffnen.
2. Oben rechts auf **Menüpunkt anlegen** klicken.
3. Felder ausfüllen:

   | Feld | Wert |
   |---|---|
   | Name | z. B. `Mitglieder-Historie` |
   | Beschreibung | optional, z. B. `Mitgliedsart je Jahr, Wechsel für aktuelles Jahr und Folgejahr` |
   | Übergeordneter Menüpunkt | `Administration` |
   | URL | `/adm_plugins/history/index.php` |
   | Icon | `bi-clock-history` |
   | Sichtbar für | Rolle `Administrator` |

4. Speichern.

## Bedienung

- **Jahresspalten**: Je Jahr stehen alle Mitgliedsarten, in denen die Person in diesem Jahr
  mindestens einen Tag war, in zeitlicher Reihenfolge (bei einem Wechsel im Jahr also z. B. `J A`).
  Grundlage sind alle, auch beendete, Mitgliedschaften in den konfigurierten Rollen. War die Person
  in einem Jahr nur in einer gemeinsamen Rolle (`commonRoles`, z. B. „Mitglied“), ohne dass sich eine
  Mitgliedsart ermitteln lässt, zeigt die Spalte `?` (auch neben der Auswahl im aktuellen Jahr und
  Folgejahr). Sortierbar ist die Tabelle nur nach Vor- und Nachname.
- **Kontakte**: Auswahl über der Tabelle, welche Personen erscheinen: „Aktive Kontakte“ (Standard)
  sind heute in einer Rolle einer Mitgliedsart oder in einer gemeinsamen Rolle, „Ehemalige Kontakte“
  waren es früher, sind es heute aber nicht mehr, „Alle Kontakte“ zeigt beide.
- **Jahre zurück**: Auswahl über der Tabelle, wie viele Jahre vor dem aktuellen Jahr angezeigt
  werden (Standard aus `mitgliedsarten.php`, „alle Jahre“ ab der ältesten Mitgliedschaft, auch in
  den gemeinsamen Rollen). Personen ohne Mitgliedsart und ohne `?` im angezeigten Zeitraum werden
  ausgeblendet; mit „alle Jahre“ erscheinen sie.
- **Aktuelles Jahr und Folgejahr**: Die Auswahl zeigt die Mitgliedsart am 31.12. des Jahres,
  `–` bedeutet „kein Mitglied“. Kam im Jahr noch eine weitere Mitgliedsart vor (Wechsel oder
  Austritt innerhalb des Jahres), zeigt ein Warnsymbol mit Tooltip die Einzelheiten.
- **Zusatz**: Neben dem aktuellen Jahr und dem Folgejahr steht je eine Spalte „Zusatz“ mit den
  optionalen Beitragsrollen (`optionalFeeRoles`), in denen die Person in diesem Jahr mindestens
  einen Tag ist. Beitragsrollen mit Beitragszeitraum „einmalig“ (Einstellung der Rolle in Admidio)
  zählen nur im Jahr, in dem die Mitgliedschaft beginnt, auch wenn sie in Admidio offen weiterläuft.
  Die Spalte erscheint nur, wenn optionale Beitragsrollen konfiguriert sind.
- **Zusatzbeitrag hinzufügen**: Mit dem Recht zum Ändern steht in der Spalte „Zusatz“ eine Auswahl
  `+` mit den noch fehlenden Zusatzbeiträgen der Mitgliedsart, die die Person am 31.12. des Jahres
  hat. Nach der Rückfrage beginnt die Beitragsrolle am 1. Januar des Jahres (eine am Vortag endende
  Mitgliedschaft wird fortgesetzt); einmalige Beitragsrollen enden am 31. Dezember desselben
  Jahres, alle anderen laufen offen weiter. Ohne Mitgliedsart im Jahr ist kein Zusatzbeitrag
  möglich.
- **Zusatzbeitrag entfernen**: Mit dem Recht zum Ändern trägt jedes Kürzel in der Spalte „Zusatz“
  ein `×`. Nach der Rückfrage endet die Beitragsrolle am 31. Dezember des Vorjahres; eine erst an
  oder nach dem 1. Januar des Jahres begonnene Mitgliedschaft wird gelöscht.
- **Wechsel**: Eine andere Mitgliedsart auswählen und die Rückfrage bestätigen. Die Auswahl bietet
  nur die in `mitgliedsarten.php` (`transitions`) erlaubten Ziele an, einschließlich Austritt (`–`);
  ohne erlaubtes Ziel ist sie gesperrt. Der Wechsel gilt ab dem 1. Januar des gewählten Jahres:
  - alle Rollen der neuen Mitgliedsart werden ab diesem Tag begonnen (eine am Vortag endende
    Mitgliedschaft wird fortgesetzt, eine später beginnende vorgezogen),
  - alle Rollen der übrigen Mitgliedsarten enden am 31. Dezember des Vorjahres (Mitgliedschaften, die
    erst an oder nach dem Stichtag beginnen, werden gelöscht),
  - ein bereits eingetragenes Ende der bisherigen Mitgliedschaft (geplanter Austritt) wird auf die
    neue Mitgliedschaft übernommen,
  - die gemeinsamen Rollen (`commonRoles`, z. B. „Mitglied“) werden bei Bedarf begonnen oder
    verlängert; bei `–` (kein Mitglied) enden sie ebenfalls am Vortag,
  - die Pflicht-Beitragsrollen (`mandatoryFeeRoles`) der neuen Mitgliedsart werden begonnen,
    optionale Beitragsrollen (`optionalFeeRoles`) der neuen Mitgliedsart bleiben unverändert, alle
    übrigen Beitragsrollen enden am Vortag; bei `–` enden alle Beitragsrollen,
  - einmalige Beitragsrollen werden nicht ins Wechseljahr übernommen: eine vor dem Stichtag
    begonnene endet am Vortag, eine ab dem Stichtag begonnene bleibt unverändert (der Beitrag gilt
    nur in seinem Jahr).

  Die Änderung wird ohne Neuladen der Seite gespeichert; beide änderbaren Spalten der Zeile und die
  Spalten „Zusatz“ werden danach aktualisiert. Schlägt sie fehl (z. B. fehlendes Recht an einer Rolle), erscheint die
  Fehlermeldung und die Auswahl springt zurück. Die Mitgliedschaften werden über die Admidio-Klassen
  geschrieben, damit Änderungsprotokoll und Benachrichtigungen von Admidio erhalten bleiben.
- **Profil**: Vor- und Nachname verlinken auf das Profil der Person.

## Mitgliedsarten konfigurieren

`mitgliedsarten.php` gibt ein Array zurück:

| Schlüssel | Bedeutung |
|---|---|
| `types` | Mitgliedsarten in Anzeigereihenfolge. Schlüssel ist das Kürzel (ein bis drei Zeichen), Wert ein Array mit `name` (Anzeigename), `color` (Hintergrundfarbe als CSS-Wert, z. B. `#cfe2ff`) und `roles` (eine Rolle oder Liste von Rollen). Eine Person hat die Mitgliedsart, sobald sie in einer der Rollen ist; beim Wechsel werden alle Rollen der neuen Mitgliedsart begonnen. Jede Rolle darf nur zu einer Mitgliedsart gehören. |
| `commonRoles` | Rollen, in denen jedes Mitglied unabhängig von der Mitgliedsart ist (übergeordnete Rolle, z. B. „Mitglied“). Sie erscheinen nicht als eigenes Kürzel in den Jahresspalten; ein Jahr, in dem jemand nur in einer dieser Rollen war, zeigt `?`. Beim Wechsel werden sie mitgeführt. Leer, wenn es keine solche Rolle gibt. |
| `mandatoryFeeRoles` | Pflicht-Beitragsrollen je Kürzel (Rolle oder Liste). Beim Wechsel zu der Mitgliedsart werden sie begonnen, beim Wechsel weg von ihr oder beim Austritt beendet. Mitgliedsarten ohne Pflicht-Beitragsrolle können fehlen. Beitragsrollen dürfen keine Rollen einer Mitgliedsart und keine gemeinsamen Rollen sein. |
| `optionalFeeRoles` | Optionale Beitragsrollen (Zusatzbeiträge) je Kürzel. Sie werden beim Wechsel nicht begonnen, bleiben aber bestehen, solange sie zur neuen Mitgliedsart gehören; sonst enden sie. Für das aktuelle Jahr und das Folgejahr erscheinen sie in der Spalte „Zusatz“. Rollen mit Beitragszeitraum „einmalig“ gelten nur im Jahr ihres Beginns und werden bei einem Wechsel nicht ins Folgejahr übernommen. Eine Rolle darf bei mehreren Mitgliedsarten stehen, aber nicht zugleich Pflicht und optional derselben Mitgliedsart sein. |
| `transitions` | Erlaubte Wechsel in der Auswahl: bisheriges Kürzel => neues Kürzel oder Liste neuer Kürzel. `''` steht für „kein Mitglied“ (als Schlüssel: Eintritt, als Ziel: Austritt), `'*'` für alle Ziele. Beibehalten ist immer erlaubt. Fehlt der Schlüssel, ist jeder Wechsel erlaubt; ist er vorhanden, erlaubt ein nicht genanntes bisheriges Kürzel keinen Wechsel (die Auswahl ist dann gesperrt). |
| `historyYears` | Standard für „Jahre zurück“ (0 = alle Jahre). |

Rollennamen werden ohne Beachtung der Groß-/Kleinschreibung in der aktuellen Organisation gesucht.
Fehlt eine Rolle oder ist die Datei fehlerhaft, zeigt das Plugin eine Fehlermeldung mit dem Grund.
Die mitgelieferte Datei ist zugleich das dokumentierte Beispiel.

## Entwicklung: Tests, Lint und PHPStan

Die Tests laufen ohne Admidio und decken die Klassen ohne Admidio-Abhängigkeit ab: Konfiguration
(`MembershipTypeConfig`), Jahreslogik (`YearHistory`) und Änderungsplanung
(`MembershipChangePlanner`). `MembershipRepository`, `MembershipChanger` und `HistoryTableRenderer`
enthalten die Admidio-Aufrufe; Änderungen dort werden im laufenden Admidio geprüft. Für PHPStan sind
die verwendeten Admidio-Signaturen in `stubs/admidio.php` hinterlegt.

```
composer install          # einmalig, installiert PHPUnit und PHPStan
composer lint             # php -l über alle PHP-Dateien (tools/lint.php)
composer test             # PHPUnit
composer stan             # PHPStan
composer check            # alles zusammen
```

Ohne Composer: `php tools/lint.php` und `php phpunit.phar` (Phar von https://phar.phpunit.de).

## Aufbau

```
index.php                          Controller: Rechte, Übersicht, Änderungen per fetch (mode=change, addfee, removefee; JSON)
mitgliedsarten.php                 Konfiguration der Mitgliedsarten (Kürzel, Name, Farbe, Rollen), gemeinsame Rollen
composer.json, phpunit.xml         Entwicklung (PHPUnit, PHPStan, Skripte lint/test/stan/check)
phpstan.neon, stubs/admidio.php    PHPStan-Konfiguration und Admidio-Signaturen für die Analyse
tools/lint.php                     Syntaxprüfung aller PHP-Dateien
classes/
  AdmidioContext.php               bündelt die Admidio-Objekte ($gDb, $gCurrentOrgId, ...) an einer Stelle
  MembershipType.php               Wertobjekt einer Mitgliedsart (Kürzel, Name, Farbe, Rollen)
  MembershipTypeConfig.php         liest und prüft mitgliedsarten.php
  RoleRef.php                      Wertobjekt einer Rolle (id, uuid, name)
  MembershipRepository.php         SQL-Zugriff auf Rollen, Personen, Namen und Mitgliedschaften
  HistoryLoader.php                löst die konfigurierten Rollen auf und liefert Zeiträume je Mitgliedsart
  YearHistory.php                  Jahreslogik: Mitgliedsarten je Kalenderjahr und am Jahresende
  MembershipChangePlanner.php      berechnet die Datenbankoperationen eines Wechsels (ohne Admidio)
  MembershipChanger.php            prüft Rechte und führt den Wechsel über die Admidio-Entities aus
  HistoryTableRenderer.php         Tabelle mit DataTables, Auswahl und JavaScript für den Wechsel
  Html.php                         Escaping für die HTML-Ausgabe
tests/                             PHPUnit-Tests (laufen ohne Admidio)
  bootstrap.php                    Autoloader und Tabellenkonstanten
```

## Voraussetzungen

- Admidio 5, PHP 8.2 oder neuer
- Die in `mitgliedsarten.php` genannten Rollen müssen in der aktuellen Organisation existieren und aktiv sein
