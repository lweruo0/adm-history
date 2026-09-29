<?php
/**
 * Test-Bootstrap: Autoloader für die Plugin-Klassen und die Tests sowie die Admidio-Tabellenkonstanten,
 * die in SQL-Strings verwendet werden. Ein laufendes Admidio wird nicht benötigt; getestet werden die
 * Klassen ohne Admidio-Abhängigkeit (Konfiguration, Jahreslogik, Änderungsplanung).
 */

spl_autoload_register(static function (string $class): void {
    $map = [
        'AdmHistory\\Tests\\' => __DIR__ . '/',
        'AdmHistory\\'        => dirname(__DIR__) . '/classes/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

foreach ([
    'TABLE_PREFIX'   => 'adm',
    'TBL_ROLES'      => 'adm_roles',
    'TBL_CATEGORIES' => 'adm_categories',
    'TBL_MEMBERS'    => 'adm_members',
    'TBL_USERS'      => 'adm_users',
    'TBL_USER_DATA'  => 'adm_user_data',
] as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
