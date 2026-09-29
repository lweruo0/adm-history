<?php
namespace AdmHistory;

/**
 * Wertobjekt einer Mitgliedsart aus mitgliedsarten.php.
 *
 * Eine Mitgliedsart besteht aus dem Kürzel (ein Buchstabe, z. B. „A“), dem Anzeigenamen,
 * der Hintergrundfarbe und den Admidio-Rollen, die zu dieser Mitgliedsart gehören. Eine Person
 * hat die Mitgliedsart, sobald sie in mindestens einer dieser Rollen ist; beim Wechsel werden alle
 * Rollen der neuen Mitgliedsart begonnen und alle Rollen der übrigen Mitgliedsarten beendet.
 *
 * Dazu kommen Beitragsrollen: Pflicht-Beitragsrollen werden beim Wechsel zu dieser Mitgliedsart
 * mit begonnen, optionale Beitragsrollen (Zusatzbeiträge) bleiben bestehen, wenn sie zur neuen
 * Mitgliedsart gehören, und werden sonst beendet.
 */
final class MembershipType
{
    /**
     * @param string   $key               Kürzel, wird in den Jahresspalten angezeigt
     * @param string   $name              Anzeigename (z. B. „Aktiv“)
     * @param string   $color             Hintergrundfarbe als CSS-Wert (z. B. „#cfe2ff“)
     * @param string[] $roleNames         Namen der zugehörigen Admidio-Rollen (mindestens eine)
     * @param string[] $mandatoryFeeRoles Beitragsrollen, die jedes Mitglied dieser Mitgliedsart hat
     * @param string[] $optionalFeeRoles  Beitragsrollen, die Mitglieder dieser Mitgliedsart zusätzlich haben können
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $color,
        public readonly array $roleNames,
        public readonly array $mandatoryFeeRoles = [],
        public readonly array $optionalFeeRoles = [],
    ) {
    }

    /** Kürzel und Name für Beschriftungen, z. B. „A (Aktiv)“. */
    public function label(): string
    {
        return $this->key . ' (' . $this->name . ')';
    }
}
