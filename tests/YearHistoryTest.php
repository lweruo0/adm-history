<?php
namespace AdmHistory\Tests;

use AdmHistory\MembershipTypeConfig;
use AdmHistory\YearHistory;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Jahreslogik: Welche Mitgliedsarten hatte eine Person in einem Kalenderjahr?
 */
final class YearHistoryTest extends TestCase
{
    private YearHistory $history;

    protected function setUp(): void
    {
        $config = new MembershipTypeConfig([
            'types' => [
                'A' => ['name' => 'Aktiv',  'color' => '#cfe2ff', 'roles' => ['Aktiv']],
                'J' => ['name' => 'Jugend', 'color' => '#d1e7dd', 'roles' => ['Jugend']],
                'P' => ['name' => 'Passiv', 'color' => '#f8d7da', 'roles' => ['Passiv']],
            ],
        ]);
        $this->history = new YearHistory($config);
    }

    public function testOpenMembershipCountsForEveryYearFromBegin(): void
    {
        $periods = [['type' => 'A', 'begin' => '2020-03-15', 'end' => '9999-12-31']];

        self::assertSame([], $this->history->typesInYear($periods, 2019));
        self::assertSame(['A'], $this->history->typesInYear($periods, 2020));
        self::assertSame(['A'], $this->history->typesInYear($periods, 2027));
        self::assertSame(['A'], $this->history->typesAtDate($periods, '2020-03-15'));
        self::assertSame([], $this->history->typesAtDate($periods, '2020-03-14'));
    }

    public function testEndedMembershipStopsAfterEndYear(): void
    {
        $periods = [['type' => 'P', 'begin' => '2015-01-01', 'end' => '2018-12-31']];

        self::assertSame(['P'], $this->history->typesInYear($periods, 2018));
        self::assertSame([], $this->history->typesInYear($periods, 2019));
    }

    public function testChangeWithinYearListsBothTypesInChronologicalOrder(): void
    {
        $periods = [
            ['type' => 'A', 'begin' => '2024-07-01', 'end' => '9999-12-31'],
            ['type' => 'J', 'begin' => '2018-01-01', 'end' => '2024-06-30'],
        ];

        self::assertSame(['J', 'A'], $this->history->typesInYear($periods, 2024));
        self::assertSame(['J'], $this->history->typesInYear($periods, 2023));
        self::assertSame(['A'], $this->history->typesInYear($periods, 2025));
    }

    public function testSameBeginFallsBackToConfiguredOrder(): void
    {
        $periods = [
            ['type' => 'P', 'begin' => '2020-01-01', 'end' => '9999-12-31'],
            ['type' => 'A', 'begin' => '2020-01-01', 'end' => '9999-12-31'],
        ];

        self::assertSame(['A', 'P'], $this->history->typesInYear($periods, 2021));
        self::assertSame(['A', 'P'], $this->history->typesAtDate($periods, '2021-05-05'));
    }

    public function testDuplicatePeriodsOfSameTypeAppearOnce(): void
    {
        $periods = [
            ['type' => 'A', 'begin' => '2010-01-01', 'end' => '2012-12-31'],
            ['type' => 'A', 'begin' => '2012-06-01', 'end' => '9999-12-31'],
        ];

        self::assertSame(['A'], $this->history->typesInYear($periods, 2012));
    }

    public function testYearStateUsesTypeAtYearEndAndListsOthers(): void
    {
        $periods = [
            ['type' => 'J', 'begin' => '2018-01-01', 'end' => '2026-06-30'],
            ['type' => 'A', 'begin' => '2026-07-01', 'end' => '9999-12-31'],
        ];

        self::assertSame(['value' => 'A', 'others' => ['J'], 'unknown' => false], $this->history->yearState($periods, 2026));
        self::assertSame(['value' => 'J', 'others' => [], 'unknown' => false], $this->history->yearState($periods, 2025));
        self::assertSame(['value' => 'A', 'others' => [], 'unknown' => false], $this->history->yearState($periods, 2027));
    }

    public function testYearStateOfLeaverWithinYearIsEmptyWithFormerTypeAsOther(): void
    {
        $periods = [['type' => 'P', 'begin' => '2000-01-01', 'end' => '2026-03-31']];

        self::assertSame(['value' => '', 'others' => ['P'], 'unknown' => false], $this->history->yearState($periods, 2026));
        self::assertSame(['value' => '', 'others' => [], 'unknown' => false], $this->history->yearState($periods, 2027));
    }

    public function testMemberWithoutTypeIsUnknownOnlyInYearsWithCommonRoleAndWithoutType(): void
    {
        // Mitglied seit 2010, Mitgliedsart erst ab 2015 erfasst, Austritt Mitte 2020
        $periods = [['type' => 'A', 'begin' => '2015-01-01', 'end' => '2020-06-30']];
        $common = [['begin' => '2010-05-01', 'end' => '2020-06-30']];

        self::assertFalse($this->history->isMemberWithoutType($periods, $common, 2009));
        self::assertTrue($this->history->isMemberWithoutType($periods, $common, 2010));
        self::assertTrue($this->history->isMemberWithoutType($periods, $common, 2014));
        self::assertFalse($this->history->isMemberWithoutType($periods, $common, 2015));
        self::assertFalse($this->history->isMemberWithoutType($periods, $common, 2020));
        self::assertFalse($this->history->isMemberWithoutType($periods, $common, 2021));
        self::assertFalse($this->history->isMemberWithoutType($periods, [], 2012));
    }

    public function testRolesInYearListsFeeRolesOrderedByBeginWithoutDuplicates(): void
    {
        $feePeriods = [
            ['role' => 'Bootsbeitrag', 'begin' => '2020-01-01', 'end' => '2025-12-31'],
            ['role' => 'Erstbesatz',   'begin' => '2026-01-01', 'end' => '2026-12-31'],
            ['role' => 'Bootsbeitrag', 'begin' => '2026-03-01', 'end' => '9999-12-31'],
            ['role' => 'Bootsbeitrag', 'begin' => '2027-01-01', 'end' => '9999-12-31'],
        ];

        self::assertSame(['Bootsbeitrag'], $this->history->rolesInYear($feePeriods, 2025));
        self::assertSame(['Erstbesatz', 'Bootsbeitrag'], $this->history->rolesInYear($feePeriods, 2026));
        self::assertSame(['Bootsbeitrag'], $this->history->rolesInYear($feePeriods, 2027));
        self::assertSame([], $this->history->rolesInYear($feePeriods, 2019));
        self::assertSame([], $this->history->rolesInYear([], 2026));
    }

    public function testLimitToBeginYearCutsOpenAndLaterEndsButKeepsEarlierEnds(): void
    {
        $open = ['role' => 'Erstbesatz', 'begin' => '2026-03-01', 'end' => '9999-12-31'];
        $later = ['role' => 'Erstbesatz', 'begin' => '2026-03-01', 'end' => '2028-06-30'];
        $within = ['role' => 'Erstbesatz', 'begin' => '2026-03-01', 'end' => '2026-09-30'];

        self::assertSame('2026-12-31', YearHistory::limitToBeginYear($open)['end']);
        self::assertSame('2026-12-31', YearHistory::limitToBeginYear($later)['end']);
        self::assertSame($within, YearHistory::limitToBeginYear($within));

        // einmaliger Beitrag erscheint nur im Jahr des Beginns
        $periods = [YearHistory::limitToBeginYear($open)];
        self::assertSame(['Erstbesatz'], $this->history->rolesInYear($periods, 2026));
        self::assertSame([], $this->history->rolesInYear($periods, 2027));
    }

    public function testIsMemberAtDateConsidersTypeAndCommonRoles(): void
    {
        $periods = [['type' => 'A', 'begin' => '2015-01-01', 'end' => '2020-06-30']];
        $common = [['begin' => '2010-05-01', 'end' => '2022-12-31']];

        self::assertFalse($this->history->isMemberAtDate($periods, $common, '2010-04-30'));
        self::assertTrue($this->history->isMemberAtDate($periods, $common, '2012-01-01'));
        self::assertTrue($this->history->isMemberAtDate($periods, $common, '2020-06-30'));
        self::assertTrue($this->history->isMemberAtDate($periods, $common, '2022-12-31'));
        self::assertFalse($this->history->isMemberAtDate($periods, $common, '2023-01-01'));
        self::assertFalse($this->history->isMemberAtDate($periods, [], '2021-01-01'));
        self::assertFalse($this->history->isMemberAtDate([], [], '2021-01-01'));
    }

    public function testYearStateReportsUnknownForMemberWithoutType(): void
    {
        $common = [['begin' => '2020-01-01', 'end' => '9999-12-31']];

        self::assertSame(['value' => '', 'others' => [], 'unknown' => true], $this->history->yearState([], 2026, $common));
        self::assertSame(['value' => '', 'others' => [], 'unknown' => false], $this->history->yearState([], 2019, $common));
    }

    public function testFirstYearAcrossUsers(): void
    {
        $byUser = [
            'u1' => [['type' => 'A', 'begin' => '2005-04-01', 'end' => '9999-12-31']],
            'u2' => [['type' => 'J', 'begin' => '1998-01-01', 'end' => '2001-12-31'], ['type' => 'A', 'begin' => '2002-01-01', 'end' => '9999-12-31']],
            'u3' => [],
        ];

        self::assertSame(1998, $this->history->firstYear($byUser));
        self::assertNull($this->history->firstYear(['u3' => []]));
    }
}
