<?php
/**
 * Protección para APIs - devuelve JSON en lugar de redirect
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_nombre'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'draw' => 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'No autorizado'
    ]);
    exit;
}

function getClienteId(): int
{
    return $_SESSION['cliente_id'] ?? 0;
}

function getClienteNombre(): string
{
    return $_SESSION['cliente_nombre'] ?? 'Cliente';
}

function getClienteCuit(): string
{
    return $_SESSION['cliente_cuit'] ?? '';
}
