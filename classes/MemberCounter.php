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
     * Unterjährige Ein- und Austritte: Personen, die im Jahr mindestens einen Tag in einer der Rollen
     * waren, aber nicht sowohl am 1. Januar als auch am 31. Dezember. Wer am 1. Januar noch nicht
     * dabei war, hat einen Eintritt (frühester Beginn im Jahr); wer am 31. Dezember nicht mehr dabei
     * ist, einen Austritt (spätestes Ende im Jahr); beides zugleich bei Ein- und Austritt im selben
     * Jahr. Eine Unterbrechung zwischen den Stichtagen (Austritt und Wiedereintritt im Jahr) zählt
     * nicht als Bewegung.
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param int[]                                                               $roleIds
     * @return array<int, array{usr_id:int, begin:?string, end:?string}> begin = Eintrittsdatum oder null,
     *                                                                   end = Austrittsdatum oder null
     */
    public function changesInYear(array $periods, array $roleIds, int $year): array
    {
        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';
        $wanted = array_flip($roleIds);

        $byUser = [];
        foreach ($periods as $period) {
            if (!isset($wanted[$period['rol_id']]) || $period['begin'] > $yearEnd || $period['end'] < $yearStart) {
                continue;
            }
            $user = $byUser[$period['usr_id']] ?? ['atStart' => false, 'atEnd' => false, 'firstBegin' => null, 'lastEnd' => null];
            $user['atStart'] = $user['atStart'] || $period['begin'] <= $yearStart;
            $user['atEnd'] = $user['atEnd'] || $period['end'] >= $yearEnd;
            $user['firstBegin'] = $user['firstBegin'] === null ? $period['begin'] : min($user['firstBegin'], $period['begin']);
            $user['lastEnd'] = $user['lastEnd'] === null ? $period['end'] : max($user['lastEnd'], $period['end']);
            $byUser[$period['usr_id']] = $user;
        }

        $changes = [];
        foreach ($byUser as $usrId => $user) {
            if ($user['atStart'] && $user['atEnd']) {
                continue;
            }
            $changes[] = [
                'usr_id' => $usrId,
                'begin'  => $user['atStart'] ? null : $user['firstBegin'],
                'end'    => $user['atEnd'] ? null : $user['lastEnd'],
            ];
        }

        return $changes;
    }

    /**
     * Tabelle eines Jahres: je Zeile die Anzahl der Personen am 1. Januar und am 31. Dezember sowie
     * die Anzahl der Personen mit unterjährigem Ein- oder Austritt (siehe changesInYear).
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
