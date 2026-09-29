<?php
namespace AdmHistory;

use RuntimeException;

/**
 * Verbindet Konfiguration und Datenzugriff: löst die konfigurierten Rollen auf, lädt die
 * Personen mit ihren Mitgliedschaften und übersetzt Mitgliedschaften in Zeiträume je Mitgliedsart.
 *
 * Person je Eintrag (Schlüssel usr_uuid): wie MembershipRepository, zusätzlich
 *   periods: Liste aus type (Kürzel), begin, end – nur Mitgliedschaften in Rollen einer Mitgliedsart,
 *            gemeinsame Rollen (z. B. „Mitglied“) erscheinen hier nicht
 */
final class HistoryLoader
{
    /** @var array<string, RoleRef>|null Rollenname => Rolle, wird beim ersten Zugriff geladen */
    private ?array $roles = null;

    public function __construct(
        private readonly MembershipRepository $repository,
        private readonly MembershipTypeConfig $config,
    ) {
    }

    /**
     * Alle konfigurierten Rollen (Mitgliedsarten und gemeinsame Rollen).
     * @return array<string, RoleRef> Rollenname => Rolle
     * @throws RuntimeException wenn eine Rolle fehlt
     */
    public function getRoles(): array
    {
        return $this->roles ??= $this->repository->findRolesByNames($this->config->getAllRoleNames());
    }

    /**
     * @param string[] $roleNames
     * @return int[] rol_id je Rollenname
     */
    public function getRoleIds(array $roleNames): array
    {
        $roles = $this->getRoles();
        $ids = [];
        foreach ($roleNames as $roleName) {
            if (!isset($roles[$roleName])) {
                throw new RuntimeException('Rolle „' . $roleName . '“ ist nicht konfiguriert.');
            }
            $ids[] = $roles[$roleName]->id;
        }

        return array_values(array_unique($ids));
    }

    /** @return int[] Rollen-IDs aller Mitgliedsarten */
    public function getTypeRoleIds(): array
    {
        return $this->getRoleIds($this->config->getTypeRoleNames());
    }

    /** @return int[] Rollen-IDs der gemeinsamen Rollen */
    public function getCommonRoleIds(): array
    {
        return $this->getRoleIds($this->config->getCommonRoles());
    }

    /** @return int[] Rollen-IDs aller konfigurierten Rollen */
    public function getAllRoleIds(): array
    {
        return $this->getRoleIds($this->config->getAllRoleNames());
    }

    /**
     * Alle Personen, die jemals in einer der konfigurierten Rollen waren, mit Zeiträumen je Mitgliedsart.
     * @return array<string, array<string, mixed>> usr_uuid => Person
     */
    public function loadAll(): array
    {
        $members = $this->repository->getMembersWithMemberships($this->getAllRoleIds());
        foreach ($members as &$member) {
            $member['periods'] = $this->toPeriods($member['memberships']);
        }
        unset($member);

        return $members;
    }

    /**
     * Eine Person mit ihren Mitgliedschaften in den konfigurierten Rollen; null, wenn sie nicht existiert.
     * @return array<string, mixed>|null
     */
    public function loadUser(string $usrUuid): ?array
    {
        $member = $this->repository->getMemberByUuid($usrUuid, $this->getAllRoleIds());
        if ($member !== null) {
            $member['periods'] = $this->toPeriods($member['memberships']);
        }

        return $member;
    }

    /**
     * Übersetzt Mitgliedschaften (rol_id) in Zeiträume je Mitgliedsart (Kürzel). Mitgliedschaften in
     * gemeinsamen Rollen werden ausgelassen.
     *
     * @param array<int, array{mem_id:int, rol_id:int, begin:string, end:string}> $memberships
     * @return array<int, array{type:string, begin:string, end:string}>
     */
    public function toPeriods(array $memberships): array
    {
        $typeByRoleId = [];
        foreach ($this->getRoles() as $roleName => $role) {
            $typeKey = $this->config->getTypeKeyForRole($roleName);
            if ($typeKey !== null) {
                $typeByRoleId[$role->id] = $typeKey;
            }
        }

        $periods = [];
        foreach ($memberships as $membership) {
            if (isset($typeByRoleId[$membership['rol_id']])) {
                $periods[] = [
                    'type'  => $typeByRoleId[$membership['rol_id']],
                    'begin' => $membership['begin'],
                    'end'   => $membership['end'],
                ];
            }
        }

        return $periods;
    }
}
