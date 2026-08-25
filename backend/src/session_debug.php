<?php
require_once __DIR__ . "/session_boot.php";
header('Content-Type: application/json');
echo json_encode($_SESSION);
?>
