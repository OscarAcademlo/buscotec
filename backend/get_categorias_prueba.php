<?php
// backend/get_categorias_prueba.php
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Eliminar cualquier salida previa
if (ob_get_length()) ob_clean();

// JSON de prueba
$categorias = [
    ['id' => 6, 'nombre' => 'Albañil'],
    ['id' => 5, 'nombre' => 'Carpintero'],
    ['id' => 2, 'nombre' => 'Electricistas'],
    ['id' => 4, 'nombre' => 'Gasista'],
    ['id' => 3, 'nombre' => 'Plomero']
];

// Salida limpia de JSON
echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
exit;
