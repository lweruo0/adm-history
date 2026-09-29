<?php
/**
 * Syntaxprüfung aller PHP-Dateien des Plugins mit `php -l`.
 *
 * Aufruf: php tools/lint.php   (oder: composer lint)
 * Beendet sich mit Exit-Code 1, sobald eine Datei einen Syntax- oder Compile-Fehler enthält.
 * Fängt auch Fehler, die zur Laufzeit nicht abfangbar sind (z. B. doppelt definierte Labels).
 */

$root = dirname(__DIR__);
$files = [$root . '/index.php', $root . '/mitgliedsarten.php'];
foreach (['classes', 'tests', 'tools'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

$failed = 0;
foreach ($files as $file) {
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        ++$failed;
        echo implode(PHP_EOL, $output), PHP_EOL;
    }
}

echo count($files), ' Dateien geprüft, ', $failed, ' fehlerhaft.', PHP_EOL;
exit($failed === 0 ? 0 : 1);
