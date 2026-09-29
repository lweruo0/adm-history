<?php
namespace AdmHistory;

/**
 * Reine Rechenlogik: Welche Mitgliedsarten hatte eine Person in einem Kalenderjahr?
 *
 * Grundlage sind die Mitgliedschaftszeiträume einer Person, jeweils mit dem Kürzel der
 * Mitgliedsart, Beginn und Ende (Y-m-d, offen = 9999-12-31). Die Klasse kennt kein Admidio
 * und ist vollständig durch Unit-Tests abgedeckt.
 *
 * Zeitraum je Eintrag: ['type' => 'A', 'begin' => '2020-01-01', 'end' => '9999-12-31']
 */
final class YearHistory
{
    public const OPEN_END = '9999-12-31';

    public function __construct(private readonly MembershipTypeConfig $config)
    {
    }

    /**
     * Alle Mitgliedsarten, in denen die Person im Jahr mindestens einen Tag war, sortiert nach
     * dem Beginn der jeweiligen Mitgliedschaft (bei gleichem Beginn nach Konfigurationsreihenfolge),
     * ohne Doppelte.
     *
     * @param array<int, array{type:string, begin:string, end:string}> $periods
     * @return string[] Kürzel
     */
    public function typesInYear(array $periods, int $year): array
    {
        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';

        $hits = [];
        foreach ($periods as $period) {
            if ($period['begin'] <= $yearEnd && $period['end'] >= $yearStart) {
                $type = $period['type'];
                $begin = max($period['begin'], $yearStart);
                if (!isset($hits[$type]) || $begin < $hits[$type]) {
                    $hits[$type] = $begin;
                }
            }
        }

        return $this->sortKeys($hits);
    }

    /**
     * Mitgliedsarten, die an einem Tag aktiv sind, in Konfigurationsreihenfolge (normalerweise
     * höchstens eine; mehrere bedeuten inkonsistente Daten).
     *
     * @param array<int, array{type:string, begin:string, end:string}> $periods
     * @return string[] Kürzel
     */
    public function typesAtDate(array $periods, string $date): array
    {
        $hits = [];
        foreach ($periods as $period) {
            if ($period['begin'] <= $date && $period['end'] >= $date) {
                $hits[$period['type']] = $date;
            }
        }

        return $this->sortKeys($hits);
    }

    /**
     * War die Person im Jahr mindestens einen Tag in einem der Zeiträume (z. B. der gemeinsamen Rollen)?
     *
     * @param array<int, array{begin:string, end:string}> $periods
     */
    public function hasPeriodInYear(array $periods, int $year): bool
    {
        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';
        foreach ($periods as $period) {
            if ($period['begin'] <= $yearEnd && $period['end'] >= $yearStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * War die Person im Jahr Mitglied (gemeinsame Rolle, z. B. „Mitglied“), ohne dass sich eine
     * Mitgliedsart ermitteln lässt? Solche Jahre werden in der Tabelle mit „?“ gekennzeichnet.
     *
     * @param array<int, array{type:string, begin:string, end:string}> $periods       Zeiträume je Mitgliedsart
     * @param array<int, array{begin:string, end:string}>              $commonPeriods Zeiträume in den gemeinsamen Rollen
     */
    public function isMemberWithoutType(array $periods, array $commonPeriods, int $year): bool
    {
        return $this->typesInYear($periods, $year) === [] && $this->hasPeriodInYear($commonPeriods, $year);
    }

    /**
     * Zustand eines Jahres für die Auswahl: Mitgliedsart am Jahresende (31.12.), alle weiteren
     * Mitgliedsarten, die im Jahr vorkamen (z. B. bei einem Wechsel innerhalb des Jahres oder
     * einem Austritt im Jahr), und ob die Person im Jahr Mitglied ohne ermittelbare Mitgliedsart war.
     *
     * @param array<int, array{type:string, begin:string, end:string}> $periods
     * @param array<int, array{begin:string, end:string}>              $commonPeriods Zeiträume in den gemeinsamen Rollen
     * @return array{value:string, others:string[], unknown:bool} value = Kürzel am 31.12. oder '' (kein Mitglied)
     */
    public function yearState(array $periods, int $year, array $commonPeriods = []): array
    {
        $atEnd = $this->typesAtDate($periods, $year . '-12-31');
        $value = $atEnd[0] ?? '';
        $others = array_values(array_filter(
            $this->typesInYear($periods, $year),
            static fn(string $type): bool => $type !== $value
        ));

        return [
            'value'   => $value,
            'others'  => $others,
            'unknown' => $this->isMemberWithoutType($periods, $commonPeriods, $year),
        ];
    }

    /**
     * Erstes Jahr, in dem irgendeine der übergebenen Personen in einem der Zeiträume war, oder null.
     *
     * @param array<array-key, array<int, array{begin:string, end:string}>> $periodsByUser
     */
    public function firstYear(array $periodsByUser): ?int
    {
        $first = null;
        foreach ($periodsByUser as $periods) {
            foreach ($periods as $period) {
                $year = (int) substr($period['begin'], 0, 4);
                if ($first === null || $year < $first) {
                    $first = $year;
                }
            }
        }

        return $first;
    }

    /**
     * @param array<string, string> $hits Kürzel => Sortierdatum
     * @return string[]
     */
    private function sortKeys(array $hits): array
    {
        uksort($hits, fn(string $a, string $b): int =>
            [$hits[$a], $this->config->getPosition($a)] <=> [$hits[$b], $this->config->getPosition($b)]);

        return array_keys($hits);
    }
}
