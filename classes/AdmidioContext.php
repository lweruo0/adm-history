<?php
namespace AdmHistory;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;

/**
 * Bündelt die Admidio-Objekte, die das Plugin benötigt.
 *
 * Der Kontext wird einmal in index.php aus den globalen Variablen erzeugt und an Repository,
 * Renderer und Änderungslogik weitergereicht. Alle anderen Klassen greifen nie direkt auf
 * $gDb, $gCurrentUser usw. zu.
 */
final class AdmidioContext
{
    public function __construct(
        public readonly Database $db,
        public readonly int $organizationId,
        public readonly ProfileFields $profileFields,
        public readonly Language $l10n,
        public readonly User $currentUser,
    ) {
    }

    /** Erzeugt den Kontext aus den globalen Admidio-Variablen (nach system/common.php). */
    public static function fromGlobals(): self
    {
        global $gDb, $gCurrentOrgId, $gProfileFields, $gL10n, $gCurrentUser;

        return new self(
            db:             $gDb,
            organizationId: (int) $gCurrentOrgId,
            profileFields:  $gProfileFields,
            l10n:           $gL10n,
            currentUser:    $gCurrentUser,
        );
    }
}
