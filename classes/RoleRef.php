<?php
namespace AdmHistory;

/**
 * Leichtgewichtige Referenz auf eine Admidio-Rolle (ID, UUID, Name).
 *
 * Ersetzt das Admidio-Entity Role überall dort, wo nur die Kennung gebraucht wird, damit die
 * Logikklassen ohne laufendes Admidio getestet werden können.
 */
final class RoleRef
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $name,
    ) {
    }
}
