<?php
namespace AdmHistory;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Reine Rechenlogik für einen Wechsel der Mitgliedsart zu einem Stichtag.
 *
 * Aus den vorhandenen Mitgliedschaften einer Person, den zu beendenden und den gewünschten
 * Rollen wird eine Liste von Datenbankoperationen berechnet, die MembershipChanger anschließend
 * über die Admidio-Entities ausführt. Die Klasse kennt kein Admidio und ist durch Unit-Tests
 * abgedeckt.
 *
 * Regeln (Stichtag D, Vortag D-1):
 *   - Zu beendende Rollen: jede Mitgliedschaft, die D erreicht, endet am Vortag; beginnt sie erst
 *     an oder nach D, wird sie gelöscht.
 *   - Das Ende der neuen Mitgliedschaften ist das späteste Ende der beendeten Mitgliedschaften
 *     (ein geplanter Austritt bleibt damit erhalten), sonst offen (9999-12-31).
 *   - Gewünschte Rollen: eine Mitgliedschaft, die D abdeckt, wird bei Bedarf verlängert; eine
 *     Mitgliedschaft, die am Vortag endet, wird fortgesetzt; eine erst später beginnende wird auf D
 *     vorgezogen; sonst wird eine neue Mitgliedschaft ab D angelegt. Dadurch überflüssig gewordene
 *     spätere Mitgliedschaften derselben Rolle werden gelöscht.
 *
 * Mitgliedschaft je Eintrag: ['mem_id' => 1, 'rol_id' => 5, 'begin' => 'Y-m-d', 'end' => 'Y-m-d']
 * Operation: ['action' => 'update', 'mem_id' => 1, 'begin' => ..., 'end' => ...]
 *            ['action' => 'delete', 'mem_id' => 1]
 *            ['action' => 'insert', 'rol_id' => 5, 'begin' => ..., 'end' => ...]
 */
final class MembershipChangePlanner
{
    /**
     * @param array<int, array{mem_id:int, rol_id:int, begin:string, end:string}> $existing Mitgliedschaften der Person in allen betroffenen Rollen
     * @param int[]  $stopRoleIds   Rollen, die ab dem Stichtag nicht mehr gelten sollen
     * @param int[]  $targetRoleIds Rollen, die ab dem Stichtag gelten sollen
     * @param string $effectiveDate Stichtag im Format Y-m-d
     * @param string|null $maxEnd  spätestes Ende neuer oder verlängerter Mitgliedschaften (Y-m-d), z. B.
     *                             der 31.12. des Stichtagsjahres für einmalige Beiträge; null = unbegrenzt
     * @return array<int, array<string, mixed>> Operationen in Ausführungsreihenfolge
     */
    public function plan(array $existing, array $stopRoleIds, array $targetRoleIds, string $effectiveDate, ?string $maxEnd = null): array
    {
        $stopRoleIds = array_values(array_diff($stopRoleIds, $targetRoleIds));
        $dayBefore = self::previousDay($effectiveDate);
        $cap = static fn(string $end): string => $maxEnd !== null && $end > $maxEnd ? $maxEnd : $end;
        $operations = [];

        // 1. Rollen beenden; das späteste Ende bleibt für die neuen Mitgliedschaften erhalten
        $stoppedEnds = [];
        foreach ($existing as $membership) {
            if (!in_array($membership['rol_id'], $stopRoleIds, true) || $membership['end'] < $effectiveDate) {
                continue;
            }
            $stoppedEnds[] = $membership['end'];
            if ($membership['begin'] < $effectiveDate) {
                $operations[] = ['action' => 'update', 'mem_id' => $membership['mem_id'], 'begin' => $membership['begin'], 'end' => $dayBefore];
            } else {
                $operations[] = ['action' => 'delete', 'mem_id' => $membership['mem_id']];
            }
        }
        // null = keine Mitgliedschaft beendet, neue Mitgliedschaften laufen offen
        $newEnd = $stoppedEnds === [] ? null : max($stoppedEnds);

        // 2. gewünschte Rollen ab dem Stichtag sicherstellen
        foreach ($targetRoleIds as $roleId) {
            $memberships = array_values(array_filter(
                $existing,
                static fn(array $membership): bool => $membership['rol_id'] === $roleId
            ));
            usort($memberships, static fn(array $a, array $b): int => [$a['begin'], $a['end']] <=> [$b['begin'], $b['end']]);

            $covering = null;
            $adjacent = null;
            $future = [];
            foreach ($memberships as $membership) {
                if ($membership['begin'] <= $effectiveDate && $membership['end'] >= $effectiveDate) {
                    $covering ??= $membership;
                } elseif ($membership['end'] === $dayBefore) {
                    $adjacent ??= $membership;
                } elseif ($membership['begin'] > $effectiveDate) {
                    $future[] = $membership;
                }
            }

            if ($covering !== null) {
                // bestehende Mitgliedschaft nur verlängern, wenn sie kürzer läuft als die beendete
                $end = $covering['end'];
                if ($newEnd !== null && $cap($newEnd) > $end) {
                    $end = $cap($newEnd);
                    $operations[] = ['action' => 'update', 'mem_id' => $covering['mem_id'], 'begin' => $covering['begin'], 'end' => $end];
                }
            } elseif ($adjacent !== null) {
                $end = $cap($newEnd ?? YearHistory::OPEN_END);
                $operations[] = ['action' => 'update', 'mem_id' => $adjacent['mem_id'], 'begin' => $adjacent['begin'], 'end' => $end];
            } elseif ($future !== []) {
                $first = array_shift($future);
                $end = $cap($newEnd === null ? $first['end'] : max($first['end'], $newEnd));
                $operations[] = ['action' => 'update', 'mem_id' => $first['mem_id'], 'begin' => $effectiveDate, 'end' => $end];
            } else {
                $end = $cap($newEnd ?? YearHistory::OPEN_END);
                $operations[] = ['action' => 'insert', 'rol_id' => $roleId, 'begin' => $effectiveDate, 'end' => $end];
            }

            // spätere Mitgliedschaften, die jetzt innerhalb des neuen Zeitraums liegen, sind überflüssig
            foreach ($future as $membership) {
                if ($membership['begin'] <= $end) {
                    $operations[] = ['action' => 'delete', 'mem_id' => $membership['mem_id']];
                }
            }
        }

        return $operations;
    }

    /**
     * Liefert den Vortag eines Datums im Format Y-m-d.
     * @throws InvalidArgumentException bei ungültigem Datum
     */
    public static function previousDay(string $date): string
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Ungültiges Datum „' . $date . '“.');
        }

        return $day->sub(new DateInterval('P1D'))->format('Y-m-d');
    }
}
