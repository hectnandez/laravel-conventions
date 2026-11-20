<?php

declare(strict_types=1);

/**
 * Autoloader mínimo para usar el paquete sin `composer install`.
 * Cuando se instala como dependencia se usa el autoload de Composer.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Hectnandez\\Conventions\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__.'/'.str_replace('\\', '/', $relative).'.php';

    if (is_file($file)) {
        require $file;
    }
});
