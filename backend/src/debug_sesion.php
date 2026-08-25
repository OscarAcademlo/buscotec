<?php
require_once __DIR__ . '/boot_sesion.php';
require_once __DIR__ . "/session_boot.php";
header('Content-Type: application/json; charset=utf-8');

echo json_encode($_SESSION, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
