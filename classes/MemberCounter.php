<?php
namespace AdmHistory;

/**
 * Reine Rechenlogik für die Übersichtskacheln: Wie viele Personen waren an einem Stichtag in
 * einer Rolle (oder in mindestens einer Rolle einer Gruppe von Rollen)?
 *
 * Grundlage sind Mitgliedschaftszeiträume mit Person, Rolle, Beginn und Ende (Y-m-d, offen =
 * 9999-12-31). Eine Person zählt am Stichtag, wenn Beginn <= Stichtag <= Ende gilt; sie wird je
 * Zeile nur einmal gezählt, auch wenn sie in mehreren Rollen der Zeile ist. Die Klasse kennt kein
 * Admidio und wird mit PHPUnit getestet.
 *
 * Zeitraum je Eintrag: ['usr_id' => 7, 'rol_id' => 3, 'begin' => '2020-01-01', 'end' => '9999-12-31']
 */
final class MemberCounter
{
    /** Stichtage 1. Januar und 31. Dezember des Jahres: nur unterjährige Ein- und Austritte */
    public const SCOPE_WITHIN_YEAR = 'within';

    /** Stichtage 31. Dezember des Vorjahres und 1. Januar des Folgejahres: alle Ein- und Austritte des Jahres */
    public const SCOPE_WHOLE_YEAR = 'whole';

    /**
     * Anzahl der Personen, die am Stichtag in mindestens einer der Rollen sind.
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param int[]                                                               $roleIds
     * @param string                                                              $date    Stichtag (Y-m-d)
     */
    public function countAtDate(array $periods, array $roleIds, string $date): int
    {
        $wanted = array_flip($roleIds);
        $users = [];
        foreach ($periods as $period) {
            if (isset($wanted[$period['rol_id']]) && $period['begin'] <= $date && $period['end'] >= $date) {
                $users[$period['usr_id']] = true;
            }
        }

        return count($users);
    }

    /**
     * Ein- und Austritte eines Jahres: Personen, die im Jahr mindestens einen Tag in einer der Rollen
     * waren, aber nicht an beiden Stichtagen. Wer am ersten Stichtag nicht dabei war, hat einen
     * Eintritt (frühester Beginn im Jahr); wer am zweiten Stichtag nicht mehr dabei ist, einen
     * Austritt (spätestes Ende im Jahr); beides zugleich bei Ein- und Austritt im selben Jahr. Eine
     * Unterbrechung zwischen den Stichtagen (Austritt und Wiedereintritt) zählt nicht als Bewegung.
     *
     * Die Stichtage bestimmt $scope:
     *   - SCOPE_WITHIN_YEAR: 1. Januar und 31. Dezember des Jahres, also nur unterjährige Bewegungen
     *     (ein Beginn am 1. Januar und ein Ende am 31. Dezember sind keine Bewegung);
     *   - SCOPE_WHOLE_YEAR: 31. Dezember des Vorjahres und 1. Januar des Folgejahres, also auch
     *     Eintritte zum 1. Januar und Austritte zum 31. Dezember (eine am 31. Dezember endende und
     *     am 1. Januar nahtlos fortgesetzte Mitgliedschaft ist keine Bewegung).
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param int[]                                                               $roleIds
     * @param string                                                              $scope   eine der SCOPE_*-Konstanten
     * @return array<int, array{usr_id:int, begin:?string, end:?string}> begin = Eintrittsdatum oder null,
     *                                                                   end = Austrittsdatum oder null
     */
    public function changesInYear(array $periods, array $roleIds, int $year, string $scope = self::SCOPE_WITHIN_YEAR): array
    {
        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';
        $dayBefore = $scope === self::SCOPE_WHOLE_YEAR ? ($year - 1) . '-12-31' : $yearStart;
        $dayAfter = $scope === self::SCOPE_WHOLE_YEAR ? ($year + 1) . '-01-01' : $yearEnd;
        $wanted = array_flip($roleIds);

        $byUser = [];
        foreach ($periods as $period) {
            if (!isset($wanted[$period['rol_id']])) {
                continue;
            }
            $coversBefore = $period['begin'] <= $dayBefore && $period['end'] >= $dayBefore;
            $coversAfter = $period['begin'] <= $dayAfter && $period['end'] >= $dayAfter;
            $inYear = $period['begin'] <= $yearEnd && $period['end'] >= $yearStart;
            if (!$coversBefore && !$coversAfter && !$inYear) {
                continue;
            }
            $user = $byUser[$period['usr_id']] ?? ['before' => false, 'after' => false, 'firstBegin' => null, 'lastEnd' => null];
            $user['before'] = $user['before'] || $coversBefore;
            $user['after'] = $user['after'] || $coversAfter;
            if ($inYear) {
                $begin = max($period['begin'], $yearStart);
                $end = min($period['end'], $yearEnd);
                $user['firstBegin'] = $user['firstBegin'] === null ? $begin : min($user['firstBegin'], $begin);
                $user['lastEnd'] = $user['lastEnd'] === null ? $end : max($user['lastEnd'], $end);
            }
            $byUser[$period['usr_id']] = $user;
        }

        $changes = [];
        foreach ($byUser as $usrId => $user) {
            // nicht im Jahr dabei oder vor und nach dem Jahr dabei: keine Bewegung
            if ($user['firstBegin'] === null || ($user['before'] && $user['after'])) {
                continue;
            }
            $changes[] = [
                'usr_id' => $usrId,
                'begin'  => $user['before'] ? null : $user['firstBegin'],
                'end'    => $user['after'] ? null : $user['lastEnd'],
            ];
        }

        return $changes;
    }

    /**
     * Rollen je Person, in denen sie im Jahr mindestens einen Tag war, in der Reihenfolge des Beginns
     * (für die Spalte „Rollen“ der Listen).
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param int[]                                                               $roleIds
     * @return array<int, int[]> usr_id => Rollen-IDs
     */
    public function roleIdsInYearByUser(array $periods, array $roleIds, int $year): array
    {
        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';
        $wanted = array_flip($roleIds);

        $firstBegin = [];
        foreach ($periods as $period) {
            if (!isset($wanted[$period['rol_id']]) || $period['begin'] > $yearEnd || $period['end'] < $yearStart) {
                continue;
            }
            $begin = max($period['begin'], $yearStart);
            $current = $firstBegin[$period['usr_id']][$period['rol_id']] ?? null;
            if ($current === null || $begin < $current) {
                $firstBegin[$period['usr_id']][$period['rol_id']] = $begin;
            }
        }

        $result = [];
        foreach ($firstBegin as $usrId => $byRole) {
            asort($byRole);
            $result[$usrId] = array_keys($byRole);
        }

        return $result;
    }

    /**
     * Tabelle eines Jahres: je Zeile die Anzahl der Personen am 1. Januar und am 31. Dezember sowie
     * die Anzahl der Personen mit unterjährigem Ein- oder Austritt (changesInYear mit SCOPE_WITHIN_YEAR).
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param array<string, int[]>                                                $rows    Beschriftung => Rollen-IDs
     * @return array<int, array{label:string, begin:int, end:int, changes:int}>
     */
    public function yearTable(array $periods, array $rows, int $year): array
    {
        $table = [];
        foreach ($rows as $label => $roleIds) {
            $table[] = [
                'label'   => (string) $label,
                'begin'   => $this->countAtDate($periods, $roleIds, $year . '-01-01'),
                'end'     => $this->countAtDate($periods, $roleIds, $year . '-12-31'),
                'changes' => count($this->changesInYear($periods, $roleIds, $year)),
            ];
        }

        return $table;
    }
}
