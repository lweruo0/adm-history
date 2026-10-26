<?php
namespace AdmHistory;

use RuntimeException;

/**
 * Programmierschnittstelle für externe Aufrufer (z. B. eine eigene REST-API): setzt Mitgliedsart
 * und optionale Beitragsrollen (Zusatzbeiträge) einer Person ab dem 1. Januar eines Jahres in
 * einem Aufruf. Authentifizierung und Zugriffsschutz sind Sache des Aufrufers; diese Klasse prüft
 * nur die fachlichen Regeln und nutzt MembershipChanger für die eigentlichen Änderungen, damit
 * Rechteprüfung, Änderungsprotokoll und Benachrichtigungen von Admidio erhalten bleiben.
 */
final class MembershipApi
{
    public function __construct(
        private readonly MembershipTypeConfig $config,
        private readonly HistoryLoader $loader,
        private readonly YearHistory $history,
        private readonly MembershipChanger $changer,
    ) {
    }

    /** Baut die Schnittstelle aus einem bestehenden Admidio-Kontext und der Konfigurationsdatei auf. */
    public static function fromContext(AdmidioContext $context, string $configFile = __DIR__ . '/../mitgliedsarten.php'): self
    {
        $config = MembershipTypeConfig::fromFile($configFile);
        $loader = new HistoryLoader(new MembershipRepository($context), $config);
        $history = new YearHistory($config);

        return new self($config, $loader, $history, new MembershipChanger($context, $config, $loader, $history));
    }

    /**
     * Setzt die Mitgliedsart einer Person ab dem 1. Januar des Jahres (nur aktuelles Jahr oder
     * Folgejahr, wie beim Wechsel über die Oberfläche) und sorgt danach dafür, dass genau die
     * genannten optionalen Beitragsrollen für das Jahr gelten: fehlende werden ergänzt, nicht
     * genannte entfernt. Ohne Mitgliedsart (art = '') darf $fee nur leer sein.
     *
     * @param string   $userUuid Person, UUID
     * @param int      $year     Jahr, ab dessen 1. Januar die Änderung gilt (aktuelles Jahr oder Folgejahr)
     * @param string   $art      Kürzel oder Name der neuen Mitgliedsart (Groß-/Kleinschreibung egal), '' = kein Mitglied
     * @param string[] $fee      gewünschte optionale Beitragsrollen für das Jahr (konfigurierter Name, Groß-/Kleinschreibung egal)
     * @return array{type:string, fees:string[], operations:int} Kürzel ('' = kein Mitglied) und Beitragsrollen nach der
     *                                                            Änderung, Anzahl der ausgeführten Datenbankoperationen
     * @throws RuntimeException bei unbekannter Person, unbekannter Mitgliedsart oder Beitragsrolle, nicht vorgesehenem
     *                          Wechsel, unzulässigem Jahr oder fehlenden Rechten
     */
    public function change(string $userUuid, int $year, string $art, array $fee = []): array
    {
        $currentYear = (int) date('Y');
        if ($year !== $currentYear && $year !== $currentYear + 1) {
            throw new RuntimeException('Änderungen sind nur für das aktuelle Jahr (' . $currentYear . ') und das Folgejahr möglich.');
        }

        $user = $this->loader->loadUser($userUuid);
        if ($user === null) {
            throw new RuntimeException('Die Person „' . $userUuid . '“ wurde nicht gefunden.');
        }

        $type = $this->resolveType($art);
        if ($type === null && $fee !== []) {
            throw new RuntimeException('Ohne Mitgliedsart können keine Zusatzbeiträge gesetzt werden.');
        }
        $desired = $type === null ? [] : $this->resolveFeeNames($fee);

        $operations = $this->changer->change($user, $year, $type);
        $user = $this->loader->loadUser($userUuid) ?? $user;

        foreach ($this->feeDiff($user, $year, $desired) as $step) {
            $operations += $step($user);
            $user = $this->loader->loadUser($userUuid) ?? $user;
        }

        return [
            'type'       => $type?->key ?? '',
            'fees'       => $this->history->rolesInYear($user['feePeriods'], $year),
            'operations' => $operations,
        ];
    }

    /**
     * Vergleicht die aktuell geltenden optionalen Beitragsrollen des Jahres mit den gewünschten und
     * liefert je Unterschied eine Funktion, die removeFee()/addFee() mit dem jeweils neu geladenen
     * Benutzer aufruft.
     *
     * @param array<string, mixed> $user    Person aus HistoryLoader::loadUser() (vor der Änderung)
     * @param string[]             $desired bereits aufgelöste, konfigurierte Beitragsrollennamen
     * @return array<int, callable(array<string, mixed>):int>
     */
    private function feeDiff(array $user, int $year, array $desired): array
    {
        $current = $this->history->rolesInYear($user['feePeriods'], $year);
        $desiredLower = array_map(mb_strtolower(...), $desired);
        $currentLower = array_map(mb_strtolower(...), $current);

        $steps = [];
        foreach ($current as $roleName) {
            if (!in_array(mb_strtolower($roleName), $desiredLower, true)) {
                $steps[] = fn(array $user): int => $this->changer->removeFee($user, $year, $roleName);
            }
        }
        foreach ($desired as $roleName) {
            if (!in_array(mb_strtolower($roleName), $currentLower, true)) {
                $steps[] = fn(array $user): int => $this->changer->addFee($user, $year, $roleName);
            }
        }

        return $steps;
    }

    /**
     * Löst „art“ (Kürzel oder Name, Groß-/Kleinschreibung egal) in eine konfigurierte Mitgliedsart
     * auf; '' steht für „kein Mitglied“.
     * @throws RuntimeException wenn „art“ zu keiner konfigurierten Mitgliedsart passt
     */
    private function resolveType(string $art): ?MembershipType
    {
        $art = trim($art);
        if ($art === '') {
            return null;
        }
        $wanted = mb_strtolower($art);
        foreach ($this->config->getTypes() as $key => $type) {
            if (mb_strtolower($key) === $wanted || mb_strtolower($type->name) === $wanted) {
                return $type;
            }
        }

        throw new RuntimeException('Unbekannte Mitgliedsart „' . $art . '“.');
    }

    /**
     * Prüft die gewünschten Beitragsrollen gegen die konfigurierten optionalen Beitragsrollen
     * (Groß-/Kleinschreibung egal) und liefert sie in der konfigurierten Schreibweise, ohne Doppelte.
     * Ob eine Rolle zur neuen Mitgliedsart passt, prüft erst MembershipChanger::addFee().
     *
     * @param string[] $fee
     * @return string[]
     * @throws RuntimeException bei unbekannter Beitragsrolle
     */
    private function resolveFeeNames(array $fee): array
    {
        $known = $this->config->getOptionalFeeRoleNames();
        $resolved = [];
        foreach ($fee as $roleName) {
            $wanted = mb_strtolower(trim((string) $roleName));
            $match = null;
            foreach ($known as $candidate) {
                if (mb_strtolower($candidate) === $wanted) {
                    $match = $candidate;
                    break;
                }
            }
            if ($match === null) {
                throw new RuntimeException('Unbekannte Beitragsrolle „' . $roleName . '“.');
            }
            $resolved[] = $match;
        }

        return array_values(array_unique($resolved));
    }
}
