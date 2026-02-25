<?php
/**
 * Autoloader manual para Dompdf y sus dependencias
 * (Sin Composer)
 */

spl_autoload_register(function ($class) {
    // Mapeo de namespaces a directorios
    $namespaces = [
        'Dompdf\\'       => __DIR__ . '/dompdf/src/',
        'FontLib\\'      => __DIR__ . '/php-font-lib/src/FontLib/',
        'Svg\\'          => __DIR__ . '/php-svg-lib/src/Svg/',
        'Masterminds\\'  => __DIR__ . '/html5-php/src/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Cargar Cpdf.php manualmente (no tiene namespace)
require_once __DIR__ . '/dompdf/lib/Cpdf.php';
