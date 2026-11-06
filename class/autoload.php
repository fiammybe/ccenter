<?php
/**
 * Class loader for the module: mod_ccenter_<Name> lives in class/<Name>.php.
 * ImpressCMS only autoloads its own icms_* classes, so the module registers its own.
 */
spl_autoload_register(static function ($class) {
    $prefix = 'mod_ccenter_';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
