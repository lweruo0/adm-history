<?php
namespace AdmHistory;

use Admidio\Roles\Entity\Membership;
use Admidio\Roles\Entity\Role;
use RuntimeException;
use Throwable;

/**
 * Führt einen Wechsel der Mitgliedsart über die Admidio-Entities aus.
 *
 * Der Wechsel gilt ab dem 1. Januar des gewählten Jahres. Welche Mitgliedschaften dafür
 * angelegt, gekürzt oder gelöscht werden, berechnet MembershipChangePlanner; diese Klasse prüft
 * die Rechte des angemeldeten Benutzers an allen betroffenen Rollen und schreibt die Änderungen
 * in einer Transaktion über die Membership-Entity, damit Änderungsprotokoll und Benachrichtigungen
 * von Admidio erhalten bleiben.
 */
final class MembershipChanger
{
    public function __construct(
        private readonly AdmidioContext $context,
        private readonly MembershipTypeConfig $config,
        private readonly HistoryLoader $loader,
        private readonly MembershipChangePlanner $planner = new MembershipChangePlanner(),
    ) {
    }

    /**
     * Setzt die Mitgliedsart einer Person ab dem 1. Januar des Jahres.
     *
     * @param array<string, mixed> $user    Person aus HistoryLoader::loadUser()
     * @param int                  $year    Jahr, ab dessen 1. Januar der Wechsel gilt
     * @param MembershipType|null  $newType neue Mitgliedsart oder null für „kein Mitglied“
     * @return int Anzahl der ausgeführten Datenbankoperationen (0 = nichts zu ändern)
     * @throws RuntimeException bei fehlenden Rechten oder Fehlern beim Speichern
     */
    public function change(array $user, int $year, ?MembershipType $newType): int
    {
        $effectiveDate = sprintf('%04d-01-01', $year);

        if ($newType === null) {
            $stopRoleIds = $this->loader->getAllRoleIds();
            $targetRoleIds = [];
        } else {
            $targetRoleIds = $this->loader->getRoleIds(array_merge($newType->roleNames, $this->config->getCommonRoles()));
            $stopRoleIds = array_values(array_diff($this->loader->getTypeRoleIds(), $targetRoleIds));
        }

        $operations = $this->planner->plan($user['memberships'], $stopRoleIds, $targetRoleIds, $effectiveDate);
        if ($operations === []) {
            return 0;
        }

        $this->assertAssignRights($this->affectedRoleIds($operations, $user['memberships']));

        $db = $this->context->db;
        $db->startTransaction();
        try {
            foreach ($operations as $operation) {
                $this->execute($operation, (int) $user['usr_id']);
            }
            $db->endTransaction();
        } catch (Throwable $ex) {
            $db->rollback();
            throw new RuntimeException('Die Änderung konnte nicht gespeichert werden: ' . $ex->getMessage(), 0, $ex);
        }

        return count($operations);
    }

    /**
     * @param array<int, array<string, mixed>> $operations
     * @param array<int, array{mem_id:int, rol_id:int, begin:string, end:string}> $memberships
     * @return int[] Rollen-IDs, an denen Mitgliedschaften geändert werden
     */
    private function affectedRoleIds(array $operations, array $memberships): array
    {
        $roleByMemId = [];
        foreach ($memberships as $membership) {
            $roleByMemId[$membership['mem_id']] = $membership['rol_id'];
        }

        $roleIds = [];
        foreach ($operations as $operation) {
            if ($operation['action'] === 'insert') {
                $roleIds[] = (int) $operation['rol_id'];
            } elseif (isset($roleByMemId[$operation['mem_id']])) {
                $roleIds[] = $roleByMemId[$operation['mem_id']];
            }
        }

        return array_values(array_unique($roleIds));
    }

    /**
     * Prüft über Admidio, ob der angemeldete Benutzer Mitglieder dieser Rollen zuordnen darf.
     * @param int[] $roleIds
     * @throws RuntimeException wenn das Recht an mindestens einer Rolle fehlt
     */
    private function assertAssignRights(array $roleIds): void
    {
        $denied = [];
        foreach ($roleIds as $roleId) {
            $role = new Role($this->context->db, $roleId);
            if (!$role->allowedToAssignMembers($this->context->currentUser)) {
                $denied[] = (string) $role->getValue('rol_name');
            }
        }
        if ($denied !== []) {
            throw new RuntimeException('Keine Berechtigung, Mitglieder der Rolle(n) „' . implode('“, „', $denied) . '“ zu ändern.');
        }
    }

    /**
     * Führt eine einzelne Operation des Planers über die Membership-Entity aus.
     * @param array<string, mixed> $operation
     */
    private function execute(array $operation, int $usrId): void
    {
        $db = $this->context->db;

        switch ($operation['action']) {
            case 'insert':
                $membership = new Membership($db);
                $membership->setValue('mem_rol_id', (int) $operation['rol_id']);
                $membership->setValue('mem_usr_id', $usrId);
                $membership->setValue('mem_begin', $operation['begin']);
                $membership->setValue('mem_end', $operation['end']);
                $membership->setValue('mem_leader', false);
                $membership->save();
                break;

            case 'update':
                $membership = new Membership($db, (int) $operation['mem_id']);
                $this->assertOwnMembership($membership, $usrId);
                $membership->setValue('mem_begin', $operation['begin']);
                $membership->setValue('mem_end', $operation['end']);
                $membership->save();
                break;

            case 'delete':
                $membership = new Membership($db, (int) $operation['mem_id']);
                $this->assertOwnMembership($membership, $usrId);
                $membership->delete();
                break;

            default:
                throw new RuntimeException('Unbekannte Operation „' . (string) $operation['action'] . '“.');
        }
    }

    /** Sicherheitsnetz: die geladene Mitgliedschaft muss zur bearbeiteten Person gehören. */
    private function assertOwnMembership(Membership $membership, int $usrId): void
    {
        if ((int) $membership->getValue('mem_usr_id') !== $usrId) {
            throw new RuntimeException('Mitgliedschaft gehört nicht zur ausgewählten Person.');
        }
    }
}
