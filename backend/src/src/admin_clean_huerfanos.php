<?php
require 'conexion.php';
$conn->query("DELETE FROM casos WHERE profesional_id NOT IN (SELECT id FROM profesionales)");
echo "Casos huérfanos eliminados: " . $conn->affected_rows . "<br>";

$conn->query("DELETE FROM operaciones_profesionales WHERE profesional_id NOT IN (SELECT id FROM profesionales)");
echo "Operaciones huérfanas eliminadas: " . $conn->affected_rows . "<br>";
?>
