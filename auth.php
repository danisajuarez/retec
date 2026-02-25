<?php
/**
 * Protección de páginas - Sistema por CLIENTE
 * Sistema de Facturación RETEC
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_nombre'])) {
    header('Location: login.php');
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['cliente_id']);
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

function logout(): void
{
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
