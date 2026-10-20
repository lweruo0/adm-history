<?php
namespace AdmHistory\Tests;

use AdmHistory\MemberCounter;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Zählung der Übersichtskacheln: Personen je Rolle an einem Stichtag.
 */
final class MemberCounterTest extends TestCase
{
    private MemberCounter $counter;

    /** @var array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> */
    private array $periods;

    protected function setUp(): void
    {
        $this->counter = new MemberCounter();
        $this->periods = [
            ['usr_id' => 1, 'rol_id' => 10, 'begin' => '2020-01-01', 'end' => '9999-12-31'], // Aktiv, offen
            ['usr_id' => 2, 'rol_id' => 10, 'begin' => '2024-03-01', 'end' => '2025-12-31'], // Aktiv, endet 31.12.2025
            ['usr_id' => 3, 'rol_id' => 11, 'begin' => '2025-01-01', 'end' => '2025-06-30'], // Passiv, endet im Jahr
            ['usr_id' => 3, 'rol_id' => 10, 'begin' => '2025-07-01', 'end' => '9999-12-31'], // Wechsel zu Aktiv
            ['usr_id' => 4, 'rol_id' => 11, 'begin' => '2026-01-01', 'end' => '9999-12-31'], // Passiv ab Folgejahr
            ['usr_id' => 1, 'rol_id' => 11, 'begin' => '2025-01-01', 'end' => '9999-12-31'], // Person 1 zusätzlich Passiv
        ];
    }

    public function testCountsOnlyPeriodsCoveringTheDateInclusive(): void
    {
        self::assertSame(1, $this->counter->countAtDate($this->periods, [10], '2023-12-31'));
        self::assertSame(2, $this->counter->countAtDate($this->periods, [10], '2024-03-01'));
        self::assertSame(1, $this->counter->countAtDate($this->periods, [10], '2024-02-29'));
        self::assertSame(3, $this->counter->countAtDate($this->periods, [10], '2025-12-31'));
        self::assertSame(2, $this->counter->countAtDate($this->periods, [10], '2026-01-01'));
    }

    public function testCountsEachPersonOnceAcrossSeveralRoles(): void
    {
        // Person 1 ist 2025 in beiden Rollen, Person 3 wechselt im Jahr: je einmal zählen
        self::assertSame(3, $this->counter->countAtDate($this->periods, [10, 11], '2025-01-01'));
        self::assertSame(3, $this->counter->countAtDate($this->periods, [10, 11], '2025-12-31'));
        self::assertSame(0, $this->counter->countAtDate($this->periods, [99], '2025-01-01'));
        self::assertSame(0, $this->counter->countAtDate([], [10], '2025-01-01'));
    }

    public function testChangesWithinYearIgnoreMovesOnFirstAndLastDay(): void
    {
        // 2025 Aktiv: Person 3 tritt am 01.07. ein; Person 2 endet erst am 31.12. (am Stichtag noch dabei)
        self::assertSame(
            [['usr_id' => 3, 'begin' => '2025-07-01', 'end' => null]],
            $this->counter->changesInYear($this->periods, [10], 2025)
        );
        // 2025 Passiv: Person 3 tritt am 30.06. aus; Person 1 ist ab 01.01. dabei (kein unterjähriger Eintritt)
        self::assertSame(
            [['usr_id' => 3, 'begin' => null, 'end' => '2025-06-30']],
            $this->counter->changesInYear($this->periods, [11], 2025)
        );
        // Rollengruppe: Person 3 wechselt innerhalb der Gruppe, bleibt also durchgehend Mitglied
        self::assertSame([], $this->counter->changesInYear($this->periods, [10, 11], 2025));
        // 2026: Person 2 ist zum 01.01. nicht mehr dabei und nicht im Jahr, Person 4 ab 01.01. dabei
        self::assertSame([], $this->counter->changesInYear($this->periods, [10], 2026));
        self::assertSame([], $this->counter->changesInYear($this->periods, [11], 2026));
        self::assertSame([], $this->counter->changesInYear($this->periods, [10], 2014));
    }

    public function testChangesWholeYearIncludeMovesOnFirstAndLastDay(): void
    {
        $whole = MemberCounter::SCOPE_WHOLE_YEAR;

        // 2025 Aktiv: Person 2 tritt zum 31.12. aus; Person 3 tritt am 01.07. ein
        self::assertSame([
            ['usr_id' => 2, 'begin' => null, 'end' => '2025-12-31'],
            ['usr_id' => 3, 'begin' => '2025-07-01', 'end' => null],
        ], $this->counter->changesInYear($this->periods, [10], 2025, $whole));
        // 2025 Passiv: Person 3 tritt am 01.01. ein und am 30.06. aus; Person 1 tritt am 01.01. ein
        self::assertSame([
            ['usr_id' => 3, 'begin' => '2025-01-01', 'end' => '2025-06-30'],
            ['usr_id' => 1, 'begin' => '2025-01-01', 'end' => null],
        ], $this->counter->changesInYear($this->periods, [11], 2025, $whole));
        // Rollengruppe: Person 3 wechselt innerhalb der Gruppe (Eintritt 01.01. zählt, Wechsel nicht)
        self::assertSame([
            ['usr_id' => 2, 'begin' => null, 'end' => '2025-12-31'],
            ['usr_id' => 3, 'begin' => '2025-01-01', 'end' => null],
        ], $this->counter->changesInYear($this->periods, [10, 11], 2025, $whole));
        // 2026 Aktiv: Person 2 ist seit 31.12.2025 weg, also 2026 nicht im Jahr -> keine Bewegung
        self::assertSame([], $this->counter->changesInYear($this->periods, [10], 2026, $whole));
        // 2026 Passiv: Person 4 tritt zum 01.01. ein
        self::assertSame(
            [['usr_id' => 4, 'begin' => '2026-01-01', 'end' => null]],
            $this->counter->changesInYear($this->periods, [11], 2026, $whole)
        );
    }

    public function testChangesInYearHandleEntryAndExitWithinSameYearAndInterruptions(): void
    {
        $periods = [
            ['usr_id' => 5, 'rol_id' => 10, 'begin' => '2024-02-01', 'end' => '2024-10-31'], // Ein- und Austritt 2024
            ['usr_id' => 6, 'rol_id' => 10, 'begin' => '2020-01-01', 'end' => '2024-03-31'], // Unterbrechung ...
            ['usr_id' => 6, 'rol_id' => 10, 'begin' => '2024-09-01', 'end' => '9999-12-31'], // ... und Wiedereintritt
            ['usr_id' => 7, 'rol_id' => 10, 'begin' => '2024-05-01', 'end' => '2024-05-31'], // zwei kurze Zeiträume
            ['usr_id' => 7, 'rol_id' => 10, 'begin' => '2024-08-01', 'end' => '2024-08-31'],
            ['usr_id' => 8, 'rol_id' => 10, 'begin' => '2020-01-01', 'end' => '2023-12-31'], // nahtlos fortgesetzt ...
            ['usr_id' => 8, 'rol_id' => 10, 'begin' => '2024-01-01', 'end' => '9999-12-31'], // ... keine Bewegung
            ['usr_id' => 9, 'rol_id' => 10, 'begin' => '2020-01-01', 'end' => '2024-12-31'], // Austritt zum 31.12.
        ];

        // unterjährig: Austritt zum 31.12. (Person 9) zählt nicht
        self::assertSame([
            ['usr_id' => 5, 'begin' => '2024-02-01', 'end' => '2024-10-31'],
            ['usr_id' => 7, 'begin' => '2024-05-01', 'end' => '2024-08-31'],
        ], $this->counter->changesInYear($periods, [10], 2024));
        // ganzes Jahr: Austritt zum 31.12. zählt, nahtlose Fortsetzung (Person 8) nicht
        self::assertSame([
            ['usr_id' => 5, 'begin' => '2024-02-01', 'end' => '2024-10-31'],
            ['usr_id' => 7, 'begin' => '2024-05-01', 'end' => '2024-08-31'],
            ['usr_id' => 9, 'begin' => null, 'end' => '2024-12-31'],
        ], $this->counter->changesInYear($periods, [10], 2024, MemberCounter::SCOPE_WHOLE_YEAR));
        self::assertSame([], $this->counter->changesInYear($periods, [10], 2023, MemberCounter::SCOPE_WHOLE_YEAR));
    }

    public function testRoleIdsInYearByUserOrderedByBegin(): void
    {
        $byUser = $this->counter->roleIdsInYearByUser($this->periods, [10, 11], 2025);

        self::assertSame([1 => [10, 11], 2 => [10], 3 => [11, 10]], $byUser);
        self::assertSame([1 => [10], 3 => [10]], $this->counter->roleIdsInYearByUser($this->periods, [10], 2026));
        self::assertSame([], $this->counter->roleIdsInYearByUser($this->periods, [99], 2026));
    }

    public function testYearTableKeepsRowOrderAndLabels(): void
    {
        $rows = ['Aktiv' => [10], 'Passiv' => [11], 'Alle' => [10, 11]];

        self::assertSame([
            ['label' => 'Aktiv',  'begin' => 2, 'end' => 3, 'changes' => 1],
            ['label' => 'Passiv', 'begin' => 2, 'end' => 1, 'changes' => 1],
            ['label' => 'Alle',   'begin' => 3, 'end' => 3, 'changes' => 0],
        ], $this->counter->yearTable($this->periods, $rows, 2025));

        self::assertSame([
            ['label' => 'Aktiv',  'begin' => 2, 'end' => 2, 'changes' => 0],
            ['label' => 'Passiv', 'begin' => 2, 'end' => 2, 'changes' => 0],
            ['label' => 'Alle',   'begin' => 3, 'end' => 3, 'changes' => 0],
        ], $this->counter->yearTable($this->periods, $rows, 2026));

        self::assertSame([], $this->counter->yearTable($this->periods, [], 2026));
    }
}
