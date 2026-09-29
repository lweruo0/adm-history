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
  Grundlage sind alle, auch beendete, Mitgliedschaften in den konfigurierten Rollen.
- **Jahre zurück**: Auswahl über der Tabelle, wie viele Jahre vor dem aktuellen Jahr angezeigt
  werden (Standard aus `mitgliedsarten.php`, „alle Jahre“ ab der ältesten Mitgliedschaft). Personen
  ohne Mitgliedsart im angezeigten Zeitraum werden ausgeblendet; mit „alle Jahre“ erscheinen sie.
- **Aktuelles Jahr und Folgejahr**: Die Auswahl zeigt die Mitgliedsart am 31.12. des Jahres,
  `–` bedeutet „kein Mitglied“. Kam im Jahr noch eine weitere Mitgliedsart vor (Wechsel oder
  Austritt innerhalb des Jahres), zeigt ein Warnsymbol mit Tooltip die Einzelheiten.
- **Wechsel**: Eine andere Mitgliedsart auswählen und die Rückfrage bestätigen. Der Wechsel gilt ab
  dem 1. Januar des gewählten Jahres:
  - alle Rollen der neuen Mitgliedsart werden ab diesem Tag begonnen (eine am Vortag endende
    Mitgliedschaft wird fortgesetzt, eine später beginnende vorgezogen),
  - alle Rollen der übrigen Mitgliedsarten enden am 31. Dezember des Vorjahres (Mitgliedschaften, die
    erst an oder nach dem Stichtag beginnen, werden gelöscht),
  - ein bereits eingetragenes Ende der bisherigen Mitgliedschaft (geplanter Austritt) wird auf die
    neue Mitgliedschaft übernommen,
  - die gemeinsamen Rollen (`commonRoles`, z. B. „Mitglied“) werden bei Bedarf begonnen oder
    verlängert; bei `–` (kein Mitglied) enden sie ebenfalls am Vortag.

  Die Änderung wird ohne Neuladen der Seite gespeichert; beide änderbaren Spalten der Zeile werden
  danach aktualisiert. Schlägt sie fehl (z. B. fehlendes Recht an einer Rolle), erscheint die
  Fehlermeldung und die Auswahl springt zurück. Die Mitgliedschaften werden über die Admidio-Klassen
  geschrieben, damit Änderungsprotokoll und Benachrichtigungen von Admidio erhalten bleiben.
- **Profil**: Vor- und Nachname verlinken auf das Profil der Person.

## Mitgliedsarten konfigurieren

`mitgliedsarten.php` gibt ein Array zurück:

| Schlüssel | Bedeutung |
|---|---|
| `types` | Mitgliedsarten in Anzeigereihenfolge. Schlüssel ist das Kürzel (ein bis drei Zeichen), Wert ein Array mit `name` (Anzeigename), `color` (Hintergrundfarbe als CSS-Wert, z. B. `#cfe2ff`) und `roles` (eine Rolle oder Liste von Rollen). Eine Person hat die Mitgliedsart, sobald sie in einer der Rollen ist; beim Wechsel werden alle Rollen der neuen Mitgliedsart begonnen. Jede Rolle darf nur zu einer Mitgliedsart gehören. |
| `commonRoles` | Rollen, in denen jedes Mitglied unabhängig von der Mitgliedsart ist (übergeordnete Rolle, z. B. „Mitglied“). Sie erscheinen nicht in den Jahresspalten, werden beim Wechsel aber mitgeführt. Leer, wenn es keine solche Rolle gibt. |
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
index.php                          Controller: Rechte, Übersicht, Änderung per fetch (mode=change, JSON)
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
