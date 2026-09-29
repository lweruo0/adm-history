<?php
namespace AdmHistory;

/**
 * Leichtgewichtige Referenz auf eine Admidio-Rolle (ID, UUID, Name, Beitragszeitraum).
 *
 * Ersetzt das Admidio-Entity Role überall dort, wo nur die Kennung gebraucht wird, damit die
 * Logikklassen ohne laufendes Admidio getestet werden können.
 */
final class RoleRef
{
    /** Wert von rol_cost_period für den Beitragszeitraum „einmalig“ (Admidio: Role::getCostPeriods) */
    public const COST_PERIOD_ONCE = -1;

    /**
     * @param int $costPeriod Beitragszeitraum der Rolle (rol_cost_period): -1 einmalig, 1 jährlich,
     *                        2 halbjährlich, 4 vierteljährlich, 12 monatlich, 0 ohne Angabe
     */
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $name,
        public readonly int $costPeriod = 0,
    ) {
    }

    /** Hat die Rolle den Beitragszeitraum „einmalig“? */
    public function isOneTimeFee(): bool
    {
        return $this->costPeriod === self::COST_PERIOD_ONCE;
    }
}
