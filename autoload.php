<?php
/**
 * Autoloader für die Plugin-Klassen: Namespace AdmHistory\ liegt im Ordner classes/, eine Klasse
 * je Datei mit dem Klassennamen als Dateinamen. Wird von index.php und overview.php eingebunden.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'AdmHistory\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/classes/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
