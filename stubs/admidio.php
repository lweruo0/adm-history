<?php
/**
 * Signaturen der Admidio-Klassen, -Funktionen und -Konstanten, die das Plugin verwendet.
 *
 * Diese Datei wird nur von PHPStan gelesen (phpstan.neon → scanFiles), damit die Analyse ohne
 * installiertes Admidio läuft. Sie wird nie eingebunden und muss nicht vollständig sein; die
 * Signaturen entsprechen Admidio 5 (https://github.com/Admidio/admidio/tree/v5.0).
 */

namespace {
    const ADMIDIO_URL = 'https://example.org/admidio';
    const FOLDER_PLUGINS = '/adm_plugins';
    const FOLDER_MODULES = '/modules';
    const FOLDER_LIBS = '/libs';
    const TBL_ROLES = 'adm_roles';
    const TBL_CATEGORIES = 'adm_categories';
    const TBL_MEMBERS = 'adm_members';
    const TBL_USERS = 'adm_users';
    const TBL_USER_DATA = 'adm_user_data';

    /**
     * @param array<string, mixed> $array
     * @param array<string, mixed> $options
     */
    function admFuncVariableIsValid(array $array, string $variableName, string $datatype, array $options = []): mixed
    {
    }

    function handleException(\Throwable $exception): void
    {
    }
}

namespace Admidio\Infrastructure {
    class Database
    {
        /** @param array<int, mixed> $params */
        public function queryPrepared(string $sql, array $params = [], bool $showError = true): \PDOStatement|false
        {
        }

        public function startTransaction(): bool
        {
        }

        public function endTransaction(): bool
        {
        }

        public function rollback(): bool
        {
        }
    }

    class Language
    {
        public function getLanguageIsoCode(): string
        {
        }

        /** @param array<int, string> $params */
        public function get(string $textId, array $params = []): string
        {
        }
    }

    class Exception extends \Exception
    {
        /** @param array<int, string> $params */
        public function __construct(string $message, array $params = [])
        {
        }
    }
}

namespace Admidio\Infrastructure\Utils {
    class SecurityUtils
    {
        /** @param array<string, mixed> $params */
        public static function encodeUrl(string $path, array $params = [], string $anchor = '', bool $htmlSpecialCharsEncode = true): string
        {
        }

        public static function validateCsrfToken(string $csrfToken): bool
        {
        }
    }
}

namespace Admidio\Infrastructure\Entity {
    class Entity
    {
        public function getValue(string $columnName, string $format = ''): mixed
        {
        }

        public function setValue(string $columnName, mixed $newValue, bool $checkValue = true): bool
        {
        }

        public function save(bool $updateFingerPrint = true): bool
        {
        }

        public function delete(): bool
        {
        }
    }
}

namespace Admidio\Users\Entity {
    class User extends \Admidio\Infrastructure\Entity\Entity
    {
        public function isAdministrator(): bool
        {
        }

        public function checkRolesRight(?string $right = null): bool
        {
        }
    }
}

namespace Admidio\Roles\Entity {
    class Role extends \Admidio\Infrastructure\Entity\Entity
    {
        public function __construct(\Admidio\Infrastructure\Database $database, int $rolId = 0)
        {
        }

        public function allowedToAssignMembers(\Admidio\Users\Entity\User $user): bool
        {
        }
    }

    class Membership extends \Admidio\Infrastructure\Entity\Entity
    {
        public function __construct(\Admidio\Infrastructure\Database $database, int $memId = 0)
        {
        }
    }
}

namespace Admidio\ProfileFields\ValueObjects {
    class ProfileFields
    {
        public function getProperty(string $fieldNameIntern, string $column, string $format = '', bool $withObsoleteEntries = true): mixed
        {
        }
    }
}

namespace Admidio\UI\Presenter {
    class PagePresenter
    {
        public static function withHtmlIDAndHeadline(string $id, string $headline = ''): static
        {
        }

        public function setContentFullWidth(): void
        {
        }

        public function addHtml(string $html): void
        {
        }

        public function addJavascript(string $javascriptCode, bool $executeAfterPageLoad = false): void
        {
        }

        public function addJavascriptFile(string $file): void
        {
        }

        public function addCssFile(string $file): void
        {
        }

        public function addPageFunctionsMenuItem(string $id, string $name, string $url, string $icon): void
        {
        }

        public function show(): void
        {
        }
    }
}
