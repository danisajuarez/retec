<?php
/**
 * Logout
 * Sistema de Facturación RETEC
 */

session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit;
