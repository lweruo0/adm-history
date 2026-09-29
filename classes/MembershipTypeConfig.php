<?php
namespace AdmHistory;

use InvalidArgumentException;

/**
 * Liest und prüft die Konfiguration der Mitgliedsarten (mitgliedsarten.php).
 *
 * Liefert die Mitgliedsarten in konfigurierter Reihenfolge (mit ihren Pflicht- und optionalen
 * Beitragsrollen), die gemeinsamen Rollen, die erlaubten Wechsel und die Standardanzahl der
 * angezeigten Jahre. Die Konfiguration wird beim Laden vollständig geprüft,
 * damit Tippfehler sofort als Fehlermeldung erscheinen und nicht als leere Spalten.
 */
final class MembershipTypeConfig
{
    /** @var array<string, MembershipType> Kürzel => Mitgliedsart */
    private array $types = [];

    /** @var string[] */
    private array $commonRoles;

    private int $historyYears;

    /**
     * Erlaubte Wechsel: Ausgangs-Kürzel ('' = kein Mitglied) => Ziel-Kürzel ('' = Austritt);
     * null = keine Einschränkung (Schlüssel „transitions“ fehlt)
     * @var array<string, string[]>|null
     */
    private ?array $transitions = null;

    /**
     * @param array<string, mixed> $config Inhalt von mitgliedsarten.php
     * @throws InvalidArgumentException bei ungültiger Konfiguration
     */
    public function __construct(array $config)
    {
        $types = $config['types'] ?? null;
        if (!is_array($types) || $types === []) {
            throw new InvalidArgumentException('Konfiguration: „types“ muss mindestens eine Mitgliedsart enthalten.');
        }

        $seenRoles = [];
        $typeData = [];
        foreach ($types as $key => $type) {
            $key = trim((string) $key);
            if ($key === '' || mb_strlen($key) > 3) {
                throw new InvalidArgumentException('Konfiguration: Kürzel „' . $key . '“ muss ein bis drei Zeichen lang sein.');
            }
            if (!is_array($type)) {
                throw new InvalidArgumentException('Konfiguration: Mitgliedsart „' . $key . '“ muss ein Array sein.');
            }
            $name = trim((string) ($type['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Konfiguration: Mitgliedsart „' . $key . '“ hat keinen Namen.');
            }
            $color = trim((string) ($type['color'] ?? ''));
            if (preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+|rgba?\([0-9.,\s%]+\)|hsla?\([0-9.,\s%]+\))$/', $color) !== 1) {
                throw new InvalidArgumentException('Konfiguration: Mitgliedsart „' . $key . '“ hat keine gültige Farbe (z. B. #cfe2ff).');
            }
            $roles = self::roleList($type['roles'] ?? null, 'Mitgliedsart „' . $key . '“');
            if ($roles === []) {
                throw new InvalidArgumentException('Konfiguration: Mitgliedsart „' . $key . '“ hat keine Rollen.');
            }
            foreach ($roles as $role) {
                $lower = mb_strtolower($role);
                if (isset($seenRoles[$lower])) {
                    throw new InvalidArgumentException('Konfiguration: Rolle „' . $role . '“ ist mehreren Mitgliedsarten zugeordnet.');
                }
                $seenRoles[$lower] = $key;
            }
            $typeData[$key] = ['name' => $name, 'color' => $color, 'roles' => $roles];
        }

        $this->commonRoles = self::roleList($config['commonRoles'] ?? [], '„commonRoles“');
        foreach ($this->commonRoles as $role) {
            if (isset($seenRoles[mb_strtolower($role)])) {
                throw new InvalidArgumentException('Konfiguration: gemeinsame Rolle „' . $role . '“ ist zugleich einer Mitgliedsart zugeordnet.');
            }
            $seenRoles[mb_strtolower($role)] = '';
        }

        // Beitragsrollen je Mitgliedsart; sie dürfen weder Rollen einer Mitgliedsart noch gemeinsame Rollen sein
        $mandatory = self::feeRoleMap($config['mandatoryFeeRoles'] ?? [], '„mandatoryFeeRoles“', array_keys($typeData), $seenRoles);
        $optional = self::feeRoleMap($config['optionalFeeRoles'] ?? [], '„optionalFeeRoles“', array_keys($typeData), $seenRoles);
        foreach ($typeData as $key => $data) {
            $both = array_intersect(
                array_map(mb_strtolower(...), $mandatory[$key] ?? []),
                array_map(mb_strtolower(...), $optional[$key] ?? [])
            );
            if ($both !== []) {
                throw new InvalidArgumentException('Konfiguration: Beitragsrolle „' . reset($both) . '“ ist für „' . $key . '“ zugleich Pflicht und optional.');
            }
            $this->types[$key] = new MembershipType($key, $data['name'], $data['color'], $data['roles'], $mandatory[$key] ?? [], $optional[$key] ?? []);
        }

        $historyYears = $config['historyYears'] ?? 15;
        if (!is_int($historyYears) || $historyYears < 0) {
            throw new InvalidArgumentException('Konfiguration: „historyYears“ muss eine ganze Zahl ≥ 0 sein.');
        }
        $this->historyYears = $historyYears;

        if (array_key_exists('transitions', $config)) {
            $this->transitions = $this->transitionList($config['transitions']);
        }
    }

    /**
     * Prüft „transitions“: Ausgangs-Kürzel ('' = kein Mitglied) => Ziel-Kürzel oder Liste von
     * Ziel-Kürzeln ('' = Austritt); '*' steht für alle Ziele. Das Ausgangs-Kürzel selbst wird als
     * Ziel ignoriert (Beibehalten ist immer erlaubt).
     *
     * @return array<string, string[]>
     * @throws InvalidArgumentException
     */
    private function transitionList(mixed $transitions): array
    {
        if (!is_array($transitions)) {
            throw new InvalidArgumentException('Konfiguration: „transitions“ muss ein Array sein.');
        }
        $allKeys = array_merge([''], array_keys($this->types));

        $result = [];
        foreach ($transitions as $from => $targets) {
            $from = trim((string) $from);
            if (!in_array($from, $allKeys, true)) {
                throw new InvalidArgumentException('Konfiguration: „transitions“ nennt das unbekannte Kürzel „' . $from . '“.');
            }
            if (is_string($targets)) {
                $targets = [$targets];
            }
            if (!is_array($targets)) {
                throw new InvalidArgumentException('Konfiguration: Ziele des Wechsels von „' . $from . '“ müssen ein Array sein.');
            }
            $allowed = [];
            foreach ($targets as $target) {
                if (!is_string($target)) {
                    throw new InvalidArgumentException('Konfiguration: Ziele des Wechsels von „' . $from . '“ müssen Kürzel sein.');
                }
                $target = trim($target);
                if ($target === '*') {
                    $allowed = $allKeys;
                    break;
                }
                if (!in_array($target, $allKeys, true)) {
                    throw new InvalidArgumentException('Konfiguration: Wechsel von „' . $from . '“ nennt das unbekannte Kürzel „' . $target . '“.');
                }
                $allowed[] = $target;
            }
            $result[$from] = array_values(array_filter(
                array_unique($allowed),
                static fn(string $key): bool => $key !== $from
            ));
        }

        return $result;
    }

    /**
     * Lädt die Konfigurationsdatei und erzeugt daraus die Konfiguration.
     * @throws InvalidArgumentException wenn die Datei fehlt oder kein Array liefert
     */
    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException('Konfigurationsdatei ' . basename($file) . ' fehlt.');
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new InvalidArgumentException('Konfigurationsdatei ' . basename($file) . ' muss ein Array zurückgeben.');
        }

        return new self($config);
    }

    /** @return array<string, MembershipType> Kürzel => Mitgliedsart, in konfigurierter Reihenfolge */
    public function getTypes(): array
    {
        return $this->types;
    }

    /** Mitgliedsart zu einem Kürzel oder null, wenn es nicht konfiguriert ist. */
    public function getType(string $key): ?MembershipType
    {
        return $this->types[$key] ?? null;
    }

    /** Position des Kürzels in der konfigurierten Reihenfolge (für Sortierungen). */
    public function getPosition(string $key): int
    {
        $position = array_search($key, array_keys($this->types), true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    /** @return string[] Rollen, in denen jedes Mitglied unabhängig von der Mitgliedsart ist */
    public function getCommonRoles(): array
    {
        return $this->commonRoles;
    }

    /** @return string[] Alle Rollennamen der Mitgliedsarten (ohne gemeinsame Rollen) */
    public function getTypeRoleNames(): array
    {
        $names = [];
        foreach ($this->types as $type) {
            foreach ($type->roleNames as $roleName) {
                $names[] = $roleName;
            }
        }

        return $names;
    }

    /**
     * @return string[] Alle Beitragsrollen (Pflicht und optional) aller Mitgliedsarten, ohne Doppelte
     */
    public function getAllFeeRoleNames(): array
    {
        $names = [];
        foreach ($this->types as $type) {
            $names = array_merge($names, $type->mandatoryFeeRoles, $type->optionalFeeRoles);
        }

        return array_values(array_unique($names));
    }

    /** @return string[] Alle optionalen Beitragsrollen (Zusatzbeiträge) aller Mitgliedsarten, ohne Doppelte */
    public function getOptionalFeeRoleNames(): array
    {
        $names = [];
        foreach ($this->types as $type) {
            $names = array_merge($names, $type->optionalFeeRoles);
        }

        return array_values(array_unique($names));
    }

    /** @return string[] Alle Rollennamen inklusive der gemeinsamen Rollen und Beitragsrollen */
    public function getAllRoleNames(): array
    {
        return array_values(array_unique(array_merge($this->getTypeRoleNames(), $this->commonRoles, $this->getAllFeeRoleNames())));
    }

    /** Kürzel der Mitgliedsart, zu der eine Rolle gehört, oder null für gemeinsame/unbekannte Rollen. */
    public function getTypeKeyForRole(string $roleName): ?string
    {
        $wanted = mb_strtolower($roleName);
        foreach ($this->types as $key => $type) {
            foreach ($type->roleNames as $name) {
                if (mb_strtolower($name) === $wanted) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Standardanzahl der angezeigten Jahre vor dem aktuellen Jahr (0 = alle). */
    public function getHistoryYears(): int
    {
        return $this->historyYears;
    }

    /**
     * Erlaubte Ziele eines Wechsels ab einer Mitgliedsart, ohne die Ausgangsart selbst, in
     * Konfigurationsreihenfolge ('' = kein Mitglied zuerst). Ohne „transitions“ sind alle Ziele
     * erlaubt; mit „transitions“ ist von einem nicht genannten Ausgangs-Kürzel kein Wechsel möglich.
     *
     * @param string $from Kürzel der bisherigen Mitgliedsart oder '' (kein Mitglied)
     * @return string[] Kürzel, '' = Austritt
     */
    public function getAllowedTargets(string $from): array
    {
        $allKeys = array_merge([''], array_keys($this->types));
        $allowed = $this->transitions === null ? $allKeys : ($this->transitions[$from] ?? []);

        return array_values(array_filter(
            $allKeys,
            static fn(string $key): bool => $key !== $from && in_array($key, $allowed, true)
        ));
    }

    /** Ist der Wechsel von $from nach $to erlaubt? Beibehalten ($from === $to) ist immer erlaubt. */
    public function isTransitionAllowed(string $from, string $to): bool
    {
        return $from === $to || in_array($to, $this->getAllowedTargets($from), true);
    }

    /**
     * Erlaubte Ziele je Ausgangs-Kürzel (für das JavaScript der Auswahl).
     * @return array<string, string[]>
     */
    public function getAllTransitions(): array
    {
        $map = [];
        foreach (array_merge([''], array_keys($this->types)) as $from) {
            $map[$from] = $this->getAllowedTargets($from);
        }

        return $map;
    }

    /**
     * Prüft eine Zuordnung Kürzel => Beitragsrollen (Rolle oder Liste von Rollen).
     *
     * @param string[]              $typeKeys      bekannte Kürzel
     * @param array<string, string> $reservedRoles Rollen (kleingeschrieben), die keine Beitragsrollen sein dürfen
     * @return array<string, string[]> Kürzel => Rollennamen
     * @throws InvalidArgumentException
     */
    private static function feeRoleMap(mixed $map, string $context, array $typeKeys, array $reservedRoles): array
    {
        if (!is_array($map)) {
            throw new InvalidArgumentException('Konfiguration: ' . $context . ' muss ein Array sein.');
        }
        $result = [];
        foreach ($map as $key => $roles) {
            $key = trim((string) $key);
            if (!in_array($key, $typeKeys, true)) {
                throw new InvalidArgumentException('Konfiguration: ' . $context . ' nennt das unbekannte Kürzel „' . $key . '“.');
            }
            $roles = self::roleList($roles, $context . ' für „' . $key . '“');
            foreach ($roles as $role) {
                if (isset($reservedRoles[mb_strtolower($role)])) {
                    throw new InvalidArgumentException('Konfiguration: Beitragsrolle „' . $role . '“ ist zugleich Rolle einer Mitgliedsart oder gemeinsame Rolle.');
                }
            }
            $result[$key] = $roles;
        }

        return $result;
    }

    /**
     * @return string[] bereinigte, eindeutige Rollennamen
     * @throws InvalidArgumentException
     */
    private static function roleList(mixed $roles, string $context): array
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        if (!is_array($roles)) {
            throw new InvalidArgumentException('Konfiguration: Rollen von ' . $context . ' müssen ein Array sein.');
        }
        $names = [];
        foreach ($roles as $role) {
            if (!is_string($role) || trim($role) === '') {
                throw new InvalidArgumentException('Konfiguration: Rollen von ' . $context . ' müssen nicht-leere Texte sein.');
            }
            $names[] = trim($role);
        }

        return array_values(array_unique($names));
    }
}
