<?php
namespace AdmHistory\Tests;

use AdmHistory\MembershipChangePlanner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Planung eines Wechsels der Mitgliedsart (Rollen 1 = Aktiv, 2 = Passiv, 9 = Mitglied).
 */
final class MembershipChangePlannerTest extends TestCase
{
    private const AKTIV = 1;
    private const PASSIV = 2;
    private const MITGLIED = 9;

    private MembershipChangePlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new MembershipChangePlanner();
    }

    /** @return array{mem_id:int, rol_id:int, begin:string, end:string} */
    private static function membership(int $memId, int $rolId, string $begin, string $end = '9999-12-31'): array
    {
        return ['mem_id' => $memId, 'rol_id' => $rolId, 'begin' => $begin, 'end' => $end];
    }

    public function testSwitchOpenMembershipToOtherTypeNextYear(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01'),
            self::membership(11, self::MITGLIED, '2010-01-01'),
        ];

        $operations = $this->planner->plan($existing, [self::AKTIV], [self::PASSIV, self::MITGLIED], '2027-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2010-01-01', 'end' => '2026-12-31'],
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2027-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testSwitchKeepsPlannedEndOfReplacedMembership(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01', '2027-12-31'),
            self::membership(11, self::MITGLIED, '2010-01-01', '2027-12-31'),
        ];

        $operations = $this->planner->plan($existing, [self::AKTIV], [self::PASSIV, self::MITGLIED], '2026-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2010-01-01', 'end' => '2025-12-31'],
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2026-01-01', 'end' => '2027-12-31'],
        ], $operations);
    }

    public function testMembershipStartingAtOrAfterEffectiveDateIsDeleted(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2027-01-01'),
            self::membership(11, self::MITGLIED, '2027-01-01'),
        ];

        $operations = $this->planner->plan($existing, [self::AKTIV], [self::PASSIV, self::MITGLIED], '2027-01-01');

        self::assertSame([
            ['action' => 'delete', 'mem_id' => 10],
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2027-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testNoChangeWhenTargetAlreadyCoversDate(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01'),
            self::membership(11, self::MITGLIED, '2010-01-01'),
        ];

        self::assertSame([], $this->planner->plan($existing, [self::PASSIV], [self::AKTIV, self::MITGLIED], '2026-01-01'));
    }

    public function testPlannedExitIsNotExtendedWhenNothingIsStopped(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01', '2026-12-31'),
            self::membership(11, self::MITGLIED, '2010-01-01', '2026-12-31'),
        ];

        self::assertSame([], $this->planner->plan($existing, [self::PASSIV], [self::AKTIV, self::MITGLIED], '2026-01-01'));
    }

    public function testCoveringCommonRoleIsExtendedToEndOfStoppedMembership(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01'),
            self::membership(11, self::MITGLIED, '2010-01-01', '2026-06-30'),
        ];

        $operations = $this->planner->plan($existing, [self::AKTIV], [self::PASSIV, self::MITGLIED], '2026-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2010-01-01', 'end' => '2025-12-31'],
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2026-01-01', 'end' => '9999-12-31'],
            ['action' => 'update', 'mem_id' => 11, 'begin' => '2010-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testLeaveEndsAllRolesAtDayBefore(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01'),
            self::membership(11, self::MITGLIED, '2010-01-01'),
            self::membership(12, self::PASSIV, '2000-01-01', '2009-12-31'),
        ];

        $operations = $this->planner->plan($existing, [self::AKTIV, self::PASSIV, self::MITGLIED], [], '2027-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2010-01-01', 'end' => '2026-12-31'],
            ['action' => 'update', 'mem_id' => 11, 'begin' => '2010-01-01', 'end' => '2026-12-31'],
        ], $operations);
    }

    public function testReentryContinuesAdjacentMembership(): void
    {
        // Austritt zum 31.12.2026 wird rückgängig gemacht: Auswahl 2027 wieder auf Aktiv
        $existing = [
            self::membership(10, self::AKTIV, '2010-01-01', '2026-12-31'),
            self::membership(11, self::MITGLIED, '2010-01-01', '2026-12-31'),
        ];

        $operations = $this->planner->plan($existing, [self::PASSIV], [self::AKTIV, self::MITGLIED], '2027-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2010-01-01', 'end' => '9999-12-31'],
            ['action' => 'update', 'mem_id' => 11, 'begin' => '2010-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testFutureMembershipIsPulledForwardAndLaterDuplicatesDeleted(): void
    {
        $existing = [
            self::membership(10, self::AKTIV, '2027-01-01', '2027-12-31'),
            self::membership(12, self::AKTIV, '2028-01-01'),
            self::membership(11, self::MITGLIED, '2026-01-01'),
        ];

        $operations = $this->planner->plan($existing, [self::PASSIV], [self::AKTIV, self::MITGLIED], '2026-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2026-01-01', 'end' => '2027-12-31'],
        ], $operations);

        $existing[0]['end'] = '9999-12-31';
        $operations = $this->planner->plan($existing, [self::PASSIV], [self::AKTIV, self::MITGLIED], '2026-01-01');

        self::assertSame([
            ['action' => 'update', 'mem_id' => 10, 'begin' => '2026-01-01', 'end' => '9999-12-31'],
            ['action' => 'delete', 'mem_id' => 12],
        ], $operations);
    }

    public function testNewMemberWithoutAnyMembership(): void
    {
        $operations = $this->planner->plan([], [self::AKTIV, self::PASSIV], [self::PASSIV, self::MITGLIED], '2026-01-01');

        self::assertSame([
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2026-01-01', 'end' => '9999-12-31'],
            ['action' => 'insert', 'rol_id' => self::MITGLIED, 'begin' => '2026-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testMembershipsEndedBeforeEffectiveDateAreUntouched(): void
    {
        $existing = [self::membership(10, self::AKTIV, '2000-01-01', '2020-12-31')];

        $operations = $this->planner->plan($existing, [self::AKTIV], [self::PASSIV], '2026-01-01');

        self::assertSame([
            ['action' => 'insert', 'rol_id' => self::PASSIV, 'begin' => '2026-01-01', 'end' => '9999-12-31'],
        ], $operations);
    }

    public function testPreviousDay(): void
    {
        self::assertSame('2025-12-31', MembershipChangePlanner::previousDay('2026-01-01'));
        self::assertSame('2024-02-29', MembershipChangePlanner::previousDay('2024-03-01'));

        $this->expectException(InvalidArgumentException::class);
        MembershipChangePlanner::previousDay('2026-02-30');
    }
}
