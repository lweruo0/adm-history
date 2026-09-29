<?php
namespace AdmHistory;

use PDO;
use RuntimeException;

/**
 * SQL-Zugriff auf Rollen und Mitgliedschaften der aktuellen Organisation.
 *
 * Alle Datenbankabfragen des Plugins liegen hier. Geliefert werden einfache Arrays, damit die
 * übrigen Klassen ohne Admidio getestet werden können.
 *
 * Person je Eintrag (Schlüssel usr_uuid):
 *   usr_id, usr_uuid, first_name, last_name,
 *   memberships: Liste aus mem_id, rol_id, begin, end (Y-m-d, offen = 9999-12-31), aufsteigend nach Beginn
 */
final class MembershipRepository
{
    public function __construct(private readonly AdmidioContext $context)
    {
    }

    /**
     * Sucht aktive Rollen der aktuellen Organisation über ihre Namen (Groß-/Kleinschreibung egal).
     *
     * @param string[] $roleNames
     * @return array<string, RoleRef> Rollenname (wie übergeben) => Rolle
     * @throws RuntimeException wenn mindestens eine Rolle nicht existiert; alle fehlenden werden genannt
     */
    public function findRolesByNames(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        $wanted = [];
        foreach ($roleNames as $roleName) {
            $wanted[$roleName] = mb_strtolower(trim($roleName));
        }
        $keys = array_values(array_unique($wanted));

        $sql = 'SELECT rol_id, rol_uuid, rol_name, rol_cost_period
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE cat_org_id = ? -- organizationId
                   AND rol_valid  = true
                   AND LOWER(rol_name) IN (' . self::placeholders($keys) . ')';
        $byKey = [];
        foreach ($this->query($sql, array_merge([$this->context->organizationId], $keys)) as $row) {
            $byKey[mb_strtolower(trim((string) $row['rol_name']))] = new RoleRef(
                (int) $row['rol_id'],
                (string) $row['rol_uuid'],
                (string) $row['rol_name'],
                (int) ($row['rol_cost_period'] ?? 0)
            );
        }

        $found = [];
        $missing = [];
        foreach ($wanted as $roleName => $key) {
            if (isset($byKey[$key])) {
                $found[$roleName] = $byKey[$key];
            } else {
                $missing[] = $roleName;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException(
                (count($missing) === 1 ? 'Rolle ' : 'Rollen ') . '„' . implode('“, „', $missing)
                . '“ in der aktuellen Organisation nicht gefunden. Bitte mitgliedsarten.php prüfen.'
            );
        }

        return $found;
    }

    /**
     * Lädt alle gültigen Personen, die jemals in einer der Rollen waren, mit Namen und sämtlichen
     * (auch beendeten) Mitgliedschaften in diesen Rollen.
     *
     * @param int[] $roleIds
     * @return array<string, array{usr_id:int, usr_uuid:string, first_name:string, last_name:string, memberships:array<int, array{mem_id:int, rol_id:int, begin:string, end:string}>}>
     */
    public function getMembersWithMemberships(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return $this->loadMembers(
            'mem_rol_id IN (' . self::placeholders($roleIds) . ')',
            array_values($roleIds)
        );
    }

    /**
     * Lädt eine Person über ihre UUID mit allen Mitgliedschaften in den Rollen; null, wenn die
     * Person nicht existiert oder ungültig ist. Eine Person ohne Mitgliedschaft in den Rollen wird
     * mit leerer Liste geliefert.
     *
     * @param int[] $roleIds
     * @return array{usr_id:int, usr_uuid:string, first_name:string, last_name:string, memberships:array<int, array{mem_id:int, rol_id:int, begin:string, end:string}>}|null
     */
    public function getMemberByUuid(string $usrUuid, array $roleIds): ?array
    {
        $sql = 'SELECT usr_id, usr_uuid FROM ' . TBL_USERS . ' WHERE usr_uuid = ? AND usr_valid = true';
        $rows = $this->query($sql, [$usrUuid]);
        if ($rows === []) {
            return null;
        }

        if ($roleIds !== []) {
            $members = $this->loadMembers(
                'mem_usr_id = ? AND mem_rol_id IN (' . self::placeholders($roleIds) . ')',
                array_merge([(int) $rows[0]['usr_id']], array_values($roleIds))
            );
            if (isset($members[$usrUuid])) {
                return $members[$usrUuid];
            }
        }

        $names = $this->loadNames([(int) $rows[0]['usr_id']]);

        return [
            'usr_id'      => (int) $rows[0]['usr_id'],
            'usr_uuid'    => $usrUuid,
            'first_name'  => $names[(int) $rows[0]['usr_id']]['first_name'] ?? '',
            'last_name'   => $names[(int) $rows[0]['usr_id']]['last_name'] ?? '',
            'memberships' => [],
        ];
    }

    /**
     * Führt eine SELECT-Abfrage aus und liefert alle Zeilen als assoziative Arrays.
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->context->db->queryPrepared($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liefert die Platzhalterliste „?, ?, ?“ für eine IN-Klausel mit so vielen Einträgen wie $values.
     * @param array<int, mixed> $values
     */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Lädt Mitgliedschaften gültiger Benutzer nach einer WHERE-Bedingung auf adm_members und
     * ergänzt Vor- und Nachname.
     *
     * @param array<int, mixed> $params
     * @return array<string, array{usr_id:int, usr_uuid:string, first_name:string, last_name:string, memberships:array<int, array{mem_id:int, rol_id:int, begin:string, end:string}>}>
     */
    private function loadMembers(string $where, array $params): array
    {
        $sql = 'SELECT usr_id, usr_uuid, mem_id, mem_rol_id, mem_begin, mem_end
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_USERS . ' ON usr_id = mem_usr_id
                 WHERE ' . $where . '
                   AND usr_valid = true
              ORDER BY usr_id, mem_begin, mem_end';

        $byId = [];
        foreach ($this->query($sql, $params) as $row) {
            $usrId = (int) $row['usr_id'];
            $byId[$usrId] ??= [
                'usr_id'      => $usrId,
                'usr_uuid'    => (string) $row['usr_uuid'],
                'first_name'  => '',
                'last_name'   => '',
                'memberships' => [],
            ];
            $byId[$usrId]['memberships'][] = [
                'mem_id' => (int) $row['mem_id'],
                'rol_id' => (int) $row['mem_rol_id'],
                'begin'  => substr((string) $row['mem_begin'], 0, 10),
                'end'    => substr((string) $row['mem_end'], 0, 10),
            ];
        }

        $names = $this->loadNames(array_keys($byId));
        $members = [];
        foreach ($byId as $usrId => $member) {
            $member['first_name'] = $names[$usrId]['first_name'] ?? '';
            $member['last_name'] = $names[$usrId]['last_name'] ?? '';
            $members[$member['usr_uuid']] = $member;
        }

        return $members;
    }

    /**
     * Lädt Vor- und Nachname der Benutzer aus den Profilfeldern FIRST_NAME und LAST_NAME.
     *
     * @param int[] $usrIds
     * @return array<int, array{first_name:string, last_name:string}>
     */
    private function loadNames(array $usrIds): array
    {
        if ($usrIds === []) {
            return [];
        }

        $firstNameId = (int) $this->context->profileFields->getProperty('FIRST_NAME', 'usf_id');
        $lastNameId = (int) $this->context->profileFields->getProperty('LAST_NAME', 'usf_id');

        $sql = 'SELECT usd_usr_id, usd_usf_id, usd_value
                  FROM ' . TBL_USER_DATA . '
                 WHERE usd_usf_id IN (?, ?)
                   AND usd_usr_id IN (' . self::placeholders($usrIds) . ')';
        $names = [];
        foreach ($this->query($sql, array_merge([$firstNameId, $lastNameId], array_values($usrIds))) as $row) {
            $usrId = (int) $row['usd_usr_id'];
            $names[$usrId] ??= ['first_name' => '', 'last_name' => ''];
            $key = (int) $row['usd_usf_id'] === $firstNameId ? 'first_name' : 'last_name';
            $names[$usrId][$key] = (string) ($row['usd_value'] ?? '');
        }

        return $names;
    }
}
