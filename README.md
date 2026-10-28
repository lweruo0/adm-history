# Mitglieder-Historie (Admidio 5)

Admidio-Plugin, das für alle Personen die Mitgliedsart je Kalenderjahr als Tabelle zeigt:
Vorname, Nachname, Folgejahr, aktuelles Jahr, Vorjahr, ... In jeder Jahresspalte stehen die
Kürzel der Mitgliedsarten (z. B. `A`, `E`, `F`, `J`, `P` für „Aktiv“, „Ehren“, „Förder“, „Jugend“
und „Passiv“) mit eigener Hintergrundfarbe. Für das aktuelle Jahr und das Folgejahr bietet jede
Zeile eine Auswahl, mit der die Mitgliedsart ab dem 1. Januar des jeweiligen Jahres gewechselt
werden kann.

Zusätzlich liefert das Plugin Kacheln für die Admidio-Übersichtsseite: je eine für das Folgejahr,
das aktuelle Jahr und das Vorjahr mit einer kleinen Tabelle, wie viele Personen am 1. Januar und am
31. Dezember des Jahres in den konfigurierten Rollen waren (siehe „Kacheln auf der Übersichtsseite
einbinden“).

Welche Admidio-Rollen zu welcher Mitgliedsart gehören und welche Rollen in den Kacheln gezählt
werden, steht in `mitgliedsarten.php` und lässt sich dort ohne Programmierung ändern oder erweitern.

## Installation

1. Ordner nach `adm_plugins/adm-history` kopieren (der Ordnername ist frei wählbar).
2. `mitgliedsarten.php` an die eigenen Rollen anpassen (siehe unten).
3. `adm_plugins/adm-history/index.php` als Administrator aufrufen oder wie unten beschrieben im
   Admidio-Menü verlinken.
4. Optional die Kacheln mit den Mitgliederzahlen in die Übersichtsseite einbinden (siehe unten).

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
   | URL | `/adm_plugins/adm-history/index.php` |
   | Icon | `bi-clock-history` |
   | Sichtbar für | Rolle `Administrator` |

4. Speichern.

Für den Schnellwechsel (siehe „Bedienung“) auf dieselbe Weise einen zweiten Menüpunkt anlegen, z. B.
Name `Schnellwechsel`, URL `/adm_plugins/adm-history/quick_change.php`, Icon `bi-lightning`. Beide
Seiten verlinken sich zusätzlich gegenseitig über das Funktionsmenü der Seite.

### Kacheln auf der Übersichtsseite einbinden

Die Admidio-Übersichtsseite (Startseite, `modules/overview.php`) wird aus der Smarty-Vorlage
`system/overview.tpl` des Themes aufgebaut; im mitgelieferten Theme liegt sie unter
`themes/simple/templates/system/overview.tpl`. Jede Kachel ist dort ein Block aus `<div>`-Elementen
mit einem `{load_admidio_plugin ...}`-Aufruf, der eine PHP-Datei aus `adm_plugins` einbindet. Das
Plugin liefert dafür `overview.php`, das sein HTML direkt ausgibt (keine eigene Smarty-Vorlage, also
kein Zwischenspeicher für Plugin-Änderungen); das Attribut `year` wählt das Jahr der Kachel: `next`
(Folgejahr), `current` (aktuelles Jahr), `previous` (Vorjahr). Ohne Attribut stehen alle drei
Tabellen in einer Kachel.

1. `overviewRoles` in `mitgliedsarten.php` festlegen (siehe „Mitgliedsarten konfigurieren“).
2. Vorlage zum Bearbeiten wählen: Änderungen direkt in `themes/simple/...` gehen beim nächsten
   Admidio-Update verloren. Besser ein eigenes Theme anlegen (`themes/<mein-theme>/`), die Datei
   `overview.tpl` nach `themes/<mein-theme>/templates/system/overview.tpl` kopieren und das Theme in
   den Admidio-Einstellungen (Allgemein → Theme) auswählen; alle übrigen Dateien nimmt Admidio
   weiterhin aus `simple`.
3. In `overview.tpl` innerhalb von `<div class="row mb-5">` an der gewünschten Stelle (z. B. nach
   der Kachel `admidio-plugin-birthday`) je Jahr einen Block ergänzen. `plugin` ist der Ordnername
   des Plugins in `adm_plugins`:

   ```smarty
   <div class="admidio-overview-plugin col-sm-6 col-lg-4 col-xl-3" id="admidio-plugin-adm-history-next">
       <div class="card admidio-card">
           <div class="card-body">
               {load_admidio_plugin plugin="adm-history" file="overview.php" year="next"}
           </div>
       </div>
   </div>
   ```

   Für das aktuelle Jahr und das Vorjahr denselben Block mit `year="current"` bzw.
   `year="previous"` (und einer anderen `id`, z. B. `admidio-plugin-adm-history-current` und
   `admidio-plugin-adm-history-previous`) einfügen. Die Reihenfolge der Blöcke bestimmt die
   Reihenfolge der Kacheln, die Klassen `col-...` die Breite (wie bei den Admidio-Kacheln).
4. Übersichtsseite neu laden. Smarty übersetzt die geänderte Vorlage automatisch; erscheint die
   Änderung nicht, den Ordner `adm_my_files/templates/compile` leeren.

Die Zahlen sehen nur angemeldete Administratoren; `overview.php` gibt für alle anderen nichts aus.
Damit bei ihnen keine leere Kachel stehen bleibt, die Blöcke in `overview.tpl` mit
`{if $validLogin && $currentUser->isAdministrator()}` … `{/if}` umschließen (Beispiel: öffnendes
`{if ...}` vor dem ersten Kachel-Block, `{/if}` nach dem letzten). Zum Prüfen ohne Vorlagenänderung lässt sich
`adm_plugins/adm-history/overview.php?year=current` direkt im Browser aufrufen.

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
    nur in seinem Jahr),
  - gilt der Wechsel als Vereinsbeitritt (`entryFromTypes`, z. B. von Jugend zu Aktiv), beginnen die
    gemeinsamen Rollen neu am Stichtag: eine schon laufende oder am Vortag endende Mitgliedschaft
    erhält den Stichtag als Beginn, früher beendete bleiben unverändert. Zusätzlich wird das
    Beitrittsdatum im Profilfeld `entryDateField` auf den Stichtag gesetzt (dafür ist das Recht
    zum Bearbeiten des Profils nötig). Die Rückfrage weist auf beides hin.

  Die Änderung wird ohne Neuladen der Seite gespeichert; beide änderbaren Spalten der Zeile und die
  Spalten „Zusatz“ werden danach aktualisiert. Schlägt sie fehl (z. B. fehlendes Recht an einer Rolle), erscheint die
  Fehlermeldung und die Auswahl springt zurück. Die Mitgliedschaften werden über die Admidio-Klassen
  geschrieben, damit Änderungsprotokoll und Benachrichtigungen von Admidio erhalten bleiben.
- **Profil**: Vor- und Nachname verlinken auf das Profil der Person.
- **Schnellwechsel** (`quick_change.php`, nur mit dem Recht zum Ändern): Tabelle aller Personen, die
  im aktuellen Jahr oder im Folgejahr mindestens einen Tag Mitglied sind (Rolle einer Mitgliedsart
  oder gemeinsame Rolle), mit je einer Spalte für beide Jahre. Jede Spalte zeigt einen Button je
  Mitgliedsart und einen Button „Kündigung“; die Mitgliedsart am 31.12. ist farbig hervorgehoben
  („Kündigung“ rot, wenn die Person am 31.12. kein Mitglied ist). Nur die in `transitions` erlaubten
  Ziele sind aktiv. Ein Klick wechselt nach der Rückfrage ab dem 1. Januar des Jahres, mit denselben
  Regeln wie die Auswahl der Mitglieder-Historie (siehe „Wechsel“); vorhandene Zusatzbeiträge bleiben,
  soweit sie auch zur neuen Mitgliedsart gehören. Darunter stehen die optionalen Beitragsrollen der
  Mitgliedsart am 31.12. als Umschalter (vorhandene mit Häkchen); ein Klick fügt den Zusatzbeitrag ab
  dem 1. Januar hinzu oder entfernt ihn. Ohne Mitgliedsart am 31.12. sind Zusatzbeiträge nur Anzeige.
  Gespeichert wird über `MembershipApi` mit Rechteprüfung und CSRF-Token;
  danach werden beide Jahresspalten der Zeile aktualisiert. Achtung:
  Ändern eines Zusatzbeitrags setzt die Mitgliedsart am 31.12. erneut ab dem 1. Januar; gab es im Jahr
  einen Wechsel (Warnsymbol), wird er damit auf den 1. Januar vorgezogen. Die Rückfrage weist darauf hin.
- **Programmierschnittstelle für andere Plugins**: Plugins auf demselben Server (z. B. eine REST-API)
  setzen Mitgliedsart und Zusatzbeiträge einer Person für das aktuelle Jahr oder das Folgejahr in
  einem Aufruf über die Klasse `MembershipApi` (Einbindung, Eingaben und Rückgabe im Doc-Block der
  Klasse). Eine eigene HTTP-Schnittstelle bietet dieses Plugin nicht. Wer die Schnittstelle nutzen
  darf, prüft das aufrufende Plugin; es setzt vorher den API-Benutzer als `$gCurrentUser`, dessen
  Rechte an den betroffenen Rollen prüft dieses Plugin weiterhin.
- **Übersichtskacheln**: Je Kachel (Folgejahr, aktuelles Jahr, Vorjahr) eine Tabelle mit einer Zeile
  je Eintrag in `overviewRoles` und den Spalten „01.01.“ und „31.12.“: Anzahl der Personen, die am
  Stichtag in der Rolle (bei mehreren Rollen: in mindestens einer davon) sind, jede Person einmal.
  Gezählt werden gültige Personen mit Mitgliedschaft, deren Beginn am oder vor und deren Ende am
  oder nach dem Stichtag liegt; geplante Mitgliedschaften (z. B. Eintritt zum Folgejahr) zählen
  also am 1. Januar des Folgejahres. Die Überschrift der Kachel verlinkt auf die Mitglieder-Historie.
  Die Kacheln sehen nur angemeldete Administratoren.
- **Listen aus den Übersichtskacheln**: Die Listen zu Eintritten, Austritten, unterjährigen
  Bewegungen und Rollenwechseln zeigen die Mitgliedsnummer aus dem Admidio-Profilfeld
  `MEMBERNUMBER1` neben Vor- und Nachname. Fehlt eine Nummer bei einer Person, bleibt die Zelle leer.
- **Unterjährige Ein- und Austritte je Zeile**: Gab es in einer Zeile Bewegungen zwischen den
  Stichtagen, steht in der vierten Spalte ein kleiner Button mit der Anzahl der Personen. Er öffnet
  in einer neuen Registerkarte die Liste dieser Personen (Nachname, Vorname mit Link zum Profil,
  Rollen, Eintritt, Austritt). Ohne Bewegungen bleibt die Spalte leer. Aufgeführt wird, wer im Jahr
  mindestens einen Tag in der Rolle war, aber nicht am 1. Januar und am 31. Dezember zugleich: ohne
  Mitgliedschaft am 1. Januar steht das Eintrittsdatum, ohne Mitgliedschaft am 31. Dezember das
  Austrittsdatum, bei beidem beides. Ein Beginn am 1. Januar und ein Ende am 31. Dezember sind hier
  keine Bewegung (Zahlen am Stichtag erklären). Ein Wechsel zwischen mehreren Rollen derselben Zeile
  zählt nicht, ebenso wenig eine Unterbrechung zwischen den Stichtagen.
- **Buttons „Eintritte“ und „Austritte“**: Unter der Tabelle jeder Kachel, mit der Anzahl in Klammern;
  ohne Personen sind sie inaktiv. Sie öffnen dieselbe Liste für alle Rollen der Kachel zusammen, mit
  den Stichtagen 31. Dezember des Vorjahres und 1. Januar des Folgejahres: Ein Eintritt ist, wer am
  31. Dezember des Vorjahres in keiner Rolle der Kachel war und im Jahr in mindestens einer, auch
  wenn die Mitgliedschaft am 1. Januar beginnt; ein Austritt ist, wer im Jahr in mindestens einer
  war und am 1. Januar des Folgejahres in keiner mehr, auch wenn sie am 31. Dezember endet. Eine am
  31. Dezember endende und am 1. Januar nahtlos fortgesetzte Mitgliedschaft ist keine Bewegung. Ein
  Wechsel zwischen Rollen der Kachel, z. B. zum 1. Januar von Passiv zu Aktiv, ist weder Ein- noch
  Austritt. Die Spalte „Rollen“ der Liste nennt die Rollen, in denen die Person im Jahr war.
- **Button „Wechsel“**: Daneben, mit denselben Stichtagen. Aufgeführt wird, wer am 31. Dezember des
  Vorjahres und am 1. Januar des Folgejahres in mindestens einer Rolle der Kachel war, aber nicht in
  denselben Rollen, z. B. von Passiv zu Aktiv (die gemeinsame Rolle „Mitglied“ steht dann in
  „Vorher“ und „Nachher“). Die Liste zeigt die Rollen an beiden Stichtagen und das Datum des
  Wechsels (Beginn der neuen Rolle; fällt nur eine Rolle weg, deren Ende). Der Wechsel zählt in dem
  Jahr, in das dieses Datum fällt: ein Wechsel zum 1. Januar also im neuen Jahr, nicht zusätzlich
  im alten. Ein Hin- und Rückwechsel innerhalb des Jahres erscheint nicht.
- **Button „Differenz“**: Daneben, wenn `overviewDifference` zwei Zeilen der Kachel nennt (z. B.
  „Summe“ und „Mitglied“). Aufgeführt wird, wer am 1. Januar oder am 31. Dezember des Jahres nur in
  einer der beiden Zeilen ist, also in einer Rolle der einen, aber in keiner Rolle der anderen,
  z. B. in „Aktiv“ ohne „Mitglied“ oder in „Mitglied“ ohne Mitgliedsart. Die Liste zeigt je Stichtag
  die Zeile, in der die Person nur ist, mit ihren Rollen dort; ein leeres Feld bedeutet: an diesem
  Tag in beiden oder in keiner Zeile. Rollen, die in beiden Zeilen stehen (z. B. „Jugend“), ergeben
  keine Differenz. Ohne Personen ist der Button inaktiv.

## Mitgliedsarten konfigurieren

`mitgliedsarten.php` gibt ein Array zurück:

| Schlüssel | Bedeutung |
|---|---|
| `types` | Mitgliedsarten in Anzeigereihenfolge. Schlüssel ist das Kürzel (ein bis drei Zeichen), Wert ein Array mit `name` (Anzeigename), `color` (Hintergrundfarbe als CSS-Wert, z. B. `#cfe2ff`) und `roles` (eine Rolle oder Liste von Rollen). Eine Person hat die Mitgliedsart, sobald sie in einer der Rollen ist; beim Wechsel werden alle Rollen der neuen Mitgliedsart begonnen. Jede Rolle darf nur zu einer Mitgliedsart gehören. |
| `commonRoles` | Rollen, in denen jedes Mitglied unabhängig von der Mitgliedsart ist (übergeordnete Rolle, z. B. „Mitglied“). Sie erscheinen nicht als eigenes Kürzel in den Jahresspalten; ein Jahr, in dem jemand nur in einer dieser Rollen war, zeigt `?`. Beim Wechsel werden sie mitgeführt. Leer, wenn es keine solche Rolle gibt. |
| `mandatoryFeeRoles` | Pflicht-Beitragsrollen je Kürzel (Rolle oder Liste). Beim Wechsel zu der Mitgliedsart werden sie begonnen, beim Wechsel weg von ihr oder beim Austritt beendet. Mitgliedsarten ohne Pflicht-Beitragsrolle können fehlen. Beitragsrollen dürfen keine Rollen einer Mitgliedsart und keine gemeinsamen Rollen sein. |
| `optionalFeeRoles` | Optionale Beitragsrollen (Zusatzbeiträge) je Kürzel. Sie werden beim Wechsel nicht begonnen, bleiben aber bestehen, solange sie zur neuen Mitgliedsart gehören; sonst enden sie. Für das aktuelle Jahr und das Folgejahr erscheinen sie in der Spalte „Zusatz“. Rollen mit Beitragszeitraum „einmalig“ gelten nur im Jahr ihres Beginns und werden bei einem Wechsel nicht ins Folgejahr übernommen. Eine Rolle darf bei mehreren Mitgliedsarten stehen, aber nicht zugleich Pflicht und optional derselben Mitgliedsart sein. |
| `transitions` | Erlaubte Wechsel in der Auswahl: bisheriges Kürzel => neues Kürzel oder Liste neuer Kürzel. `''` steht für „kein Mitglied“ (als Schlüssel: Eintritt, als Ziel: Austritt), `'*'` für alle Ziele. Beibehalten ist immer erlaubt. Fehlt der Schlüssel, ist jeder Wechsel erlaubt; ist er vorhanden, erlaubt ein nicht genanntes bisheriges Kürzel keinen Wechsel (die Auswahl ist dann gesperrt). |
| `entryFromTypes` | Kürzel, deren Wechsel zu einer anderen Mitgliedsart als Vereinsbeitritt gilt (z. B. `['J']`: aus der Jugend). Die gemeinsamen Rollen beginnen dann neu am 1. Januar des gewählten Jahres und das Beitrittsdatum wird gesetzt. `''` steht für „kein Mitglied“ (Neueintritt). Ein Austritt ist nie ein Beitritt. Leer oder fehlend: kein Wechsel gilt als Beitritt. |
| `entryDateField` | Interner Name des Admidio-Profilfelds (Typ Datum) für das Beitrittsdatum (Administration → Profilfelder, „Interner Name“). Beim Vereinsbeitritt wird es auf den 1. Januar des gewählten Jahres gesetzt. Leer oder fehlend: das Beitrittsdatum wird nicht gesetzt. Fehlt das Feld in Admidio oder ist es kein Datumsfeld, scheitert der Wechsel mit einer Fehlermeldung. |
| `overviewRoles` | Zeilen der Kacheln auf der Übersichtsseite in Anzeigereihenfolge. Ein Eintrag ist ein Rollenname (Beschriftung = Rollenname) oder Beschriftung => Rolle bzw. Liste von Rollen; bei mehreren Rollen zählt jede Person, die in mindestens einer davon ist, einmal. Die Rollen müssen nicht zu einer Mitgliedsart gehören. Fehlt der Schlüssel, zeigt die Kachel einen Hinweis. |
| `overviewDifference` | Zwei Beschriftungen aus `overviewRoles` (z. B. `['Summe', 'Mitglied']`) für den Button „Differenz“ unter der Tabelle jeder Kachel: Personen, die am 1. Januar oder am 31. Dezember nur in einer der beiden Zeilen sind. Leer oder fehlend: kein Button. |
| `historyYears` | Standard für „Jahre zurück“ (0 = alle Jahre). |

Rollennamen werden ohne Beachtung der Groß-/Kleinschreibung in der aktuellen Organisation gesucht.
Fehlt eine Rolle oder ist die Datei fehlerhaft, zeigt das Plugin eine Fehlermeldung mit dem Grund.
Die mitgelieferte Datei ist zugleich das dokumentierte Beispiel.

## Entwicklung: Tests, Lint und PHPStan

Die Tests laufen ohne Admidio und decken die Klassen ohne Admidio-Abhängigkeit ab: Konfiguration
(`MembershipTypeConfig`), Jahreslogik (`YearHistory`), Änderungsplanung
(`MembershipChangePlanner`), Zählung und HTML der Kacheln (`MemberCounter`, `OverviewTileRenderer`).
`MembershipRepository`,
`MembershipChanger`, `HistoryTableRenderer`, `QuickChangeRenderer` und `ChangeListRenderer`
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
quick_change.php                   Schnellwechsel: Buttons je Mitgliedsart, Kündigung und Zusatzbeiträge für aktuelles Jahr und Folgejahr; Speichern über MembershipApi (mode=save; JSON)
overview.php                       Kacheln für die Admidio-Übersichtsseite (load_admidio_plugin, Attribut year) und Liste der Ein- und Austritte (mode=changes)
autoload.php                       Autoloader für die Klassen in classes/ (von index.php, overview.php und quick_change.php eingebunden; auch für andere Plugins, die MembershipApi nutzen)
mitgliedsarten.php                 Konfiguration der Mitgliedsarten (Kürzel, Name, Farbe, Rollen), gemeinsame Rollen, Kacheln
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
  MemberCounter.php                zählt Personen je Rolle am 1. Januar und 31. Dezember, ermittelt Ein- und Austritte im Jahr (ohne Admidio)
  OverviewTileRenderer.php         HTML der Kacheln (Tabelle 01.01. / 31.12. je Rolle, Button zu den Ein- und Austritten; ohne Admidio)
  ChangeListRenderer.php           Seite mit der Liste der Ein- und Austritte einer Kachelzeile
  MembershipChangePlanner.php      berechnet die Datenbankoperationen eines Wechsels (ohne Admidio)
  MembershipChanger.php            prüft Rechte und führt den Wechsel über die Admidio-Entities aus
  MembershipApi.php                Schnittstelle für Schnellwechsel und andere Plugins: Mitgliedsart und Zusatzbeiträge in einem Aufruf setzen
  HistoryTableRenderer.php         Tabelle mit DataTables, Auswahl und JavaScript für den Wechsel
  QuickChangeRenderer.php          Tabelle des Schnellwechsels mit Buttons und JavaScript
  Html.php                         Escaping für die HTML-Ausgabe
tests/                             PHPUnit-Tests (laufen ohne Admidio)
  bootstrap.php                    Autoloader und Tabellenkonstanten
```

## Voraussetzungen

- Admidio 5, PHP 8.5.12 oder neuer
- Die in `mitgliedsarten.php` genannten Rollen müssen in der aktuellen Organisation existieren und aktiv sein
