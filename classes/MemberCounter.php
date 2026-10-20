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
     * Tabelle eines Jahres: je Zeile die Anzahl der Personen am 1. Januar und am 31. Dezember.
     *
     * @param array<int, array{usr_id:int, rol_id:int, begin:string, end:string}> $periods
     * @param array<string, int[]>                                                $rows    Beschriftung => Rollen-IDs
     * @return array<int, array{label:string, begin:int, end:int}>
     */
    public function yearTable(array $periods, array $rows, int $year): array
    {
        $table = [];
        foreach ($rows as $label => $roleIds) {
            $table[] = [
                'label' => (string) $label,
                'begin' => $this->countAtDate($periods, $roleIds, $year . '-01-01'),
                'end'   => $this->countAtDate($periods, $roleIds, $year . '-12-31'),
            ];
        }

        return $table;
    }
}
