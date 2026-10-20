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

    public function testYearTableKeepsRowOrderAndLabels(): void
    {
        $rows = ['Aktiv' => [10], 'Passiv' => [11], 'Alle' => [10, 11]];

        self::assertSame([
            ['label' => 'Aktiv',  'begin' => 2, 'end' => 3],
            ['label' => 'Passiv', 'begin' => 2, 'end' => 1],
            ['label' => 'Alle',   'begin' => 3, 'end' => 3],
        ], $this->counter->yearTable($this->periods, $rows, 2025));

        self::assertSame([
            ['label' => 'Aktiv',  'begin' => 2, 'end' => 2],
            ['label' => 'Passiv', 'begin' => 2, 'end' => 2],
            ['label' => 'Alle',   'begin' => 3, 'end' => 3],
        ], $this->counter->yearTable($this->periods, $rows, 2026));

        self::assertSame([], $this->counter->yearTable($this->periods, [], 2026));
    }
}
