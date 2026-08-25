<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    var_dump($_FILES);
    echo "</pre>";

    if (!empty($_FILES['imagen']['tmp_name'])) {
        $uploadDir = __DIR__ . "/uploads_mensajes/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $destino = $uploadDir . basename($_FILES['imagen']['name']);
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
            echo "✅ Subida exitosa: " . $destino;
        } else {
            echo "❌ Error al mover el archivo";
        }
    } else {
        echo "⚠️ No se recibió archivo";
    }
} else {
?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="imagen">
    <button type="submit">Subir</button>
</form>
<?php } ?>
