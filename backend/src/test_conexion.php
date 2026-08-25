<?php
require 'conexion.php';
if ($conn) {
    echo "¡Conexión exitosa!";
} else {
    echo "Error de conexión: " . $conn->connect_error;
}
?>