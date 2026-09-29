<?php
namespace AdmHistory;

use InvalidArgumentException;

/**
 * Liest und prüft die Konfiguration der Mitgliedsarten (mitgliedsarten.php).
 *
 * Liefert die Mitgliedsarten in konfigurierter Reihenfolge, die gemeinsamen Rollen und die
 * Standardanzahl der angezeigten Jahre. Die Konfiguration wird beim Laden vollständig geprüft,
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
            $this->types[$key] = new MembershipType($key, $name, $color, $roles);
        }

        $this->commonRoles = self::roleList($config['commonRoles'] ?? [], '„commonRoles“');
        foreach ($this->commonRoles as $role) {
            if (isset($seenRoles[mb_strtolower($role)])) {
                throw new InvalidArgumentException('Konfiguration: gemeinsame Rolle „' . $role . '“ ist zugleich einer Mitgliedsart zugeordnet.');
            }
        }

        $historyYears = $config['historyYears'] ?? 15;
        if (!is_int($historyYears) || $historyYears < 0) {
            throw new InvalidArgumentException('Konfiguration: „historyYears“ muss eine ganze Zahl ≥ 0 sein.');
        }
        $this->historyYears = $historyYears;
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

    /** @return string[] Alle Rollennamen inklusive der gemeinsamen Rollen */
    public function getAllRoleNames(): array
    {
        return array_values(array_unique(array_merge($this->getTypeRoleNames(), $this->commonRoles)));
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
