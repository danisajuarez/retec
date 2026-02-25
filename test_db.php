<?php
/**
 * Test de columnas LOC_NomLocalidad
 */
require_once 'config.php';

echo "<pre>";
echo "=== TEST LOCALIDAD ===\n\n";

try {
    $pdo = getConnection();
    echo "✓ Conexion exitosa\n\n";

    // Verificar si existe LOC_NomLocalidad en sige_efc_encfac
    echo "=== COLUMNAS EN sige_efc_encfac que contengan 'LOC' ===\n";
    $cols = $pdo->query("SHOW COLUMNS FROM sige_efc_encfac LIKE '%LOC%'");
    $found = false;
    foreach ($cols as $col) {
        echo "  - " . $col['Field'] . "\n";
        $found = true;
    }
    if (!$found) {
        echo "  (ninguna columna con 'LOC' encontrada)\n";
    }

    // Buscar todas las columnas que contengan LOC
    echo "\n=== BUSCANDO columnas LOC en sige_efc_encfac ===\n";
    $cols = $pdo->query("SHOW COLUMNS FROM sige_efc_encfac");
    foreach ($cols as $col) {
        if (stripos($col['Field'], 'loc') !== false) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }

    // Verificar si existe tabla de localidades
    echo "\n=== TABLAS CON 'localidad' EN EL NOMBRE ===\n";
    $tables = $pdo->query("SHOW TABLES LIKE '%localidad%'");
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "  - " . $tableName . "\n";

        // Mostrar columnas
        $cols = $pdo->query("SHOW COLUMNS FROM `$tableName`");
        foreach ($cols as $col) {
            echo "      " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }

    // Ver ejemplo de factura con su localidad
    echo "\n=== EJEMPLO FACTURA TERCERO 1001 ===\n";
    $stmt = $pdo->query("
        SELECT EFC_IdEfc, TER_IDTercero, ter_razonsocialter, TER_DomicilioTer, LOC_IDLocalidad, LOC_NomLocalidad
        FROM sige_efc_encfac
        WHERE TER_IDTercero = 1001
        LIMIT 1
    ");
    $fac = $stmt->fetch();
    if ($fac) {
        echo "  ID Factura: " . $fac['EFC_IdEfc'] . "\n";
        echo "  Tercero: " . $fac['TER_IDTercero'] . " - " . $fac['ter_razonsocialter'] . "\n";
        echo "  Domicilio: " . $fac['TER_DomicilioTer'] . "\n";
        echo "  LOC_IDLocalidad: " . ($fac['LOC_IDLocalidad'] ?? 'NULL') . "\n";
        echo "  LOC_NomLocalidad (en factura): '" . ($fac['LOC_NomLocalidad'] ?? 'NULL') . "'\n";

        // Buscar nombre localidad en tabla localidades
        if (!empty($fac['LOC_IDLocalidad'])) {
            $locStmt = $pdo->prepare("SELECT * FROM sige_loc_localidad WHERE LOC_IDLocalidad = ?");
            $locStmt->execute([$fac['LOC_IDLocalidad']]);
            $loc = $locStmt->fetch();
            if ($loc) {
                echo "\n  Localidad en tabla sige_loc_localidad:\n";
                echo "      LOC_NomLocalidad: '" . $loc['LOC_NomLocalidad'] . "'\n";
            }
        }
    } else {
        echo "  No hay facturas para tercero 1001\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
