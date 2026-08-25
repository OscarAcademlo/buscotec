<?php
declare(strict_types=1);

function verify_password_compatible(string $plainInput, ?string $storedOrNull): bool {
    // Si está hasheada: password_verify
    if ($storedOrNull === null) return false;
    $looksHashed = str_starts_with($storedOrNull, '$2y$') || str_starts_with($storedOrNull, '$argon2');
    if ($looksHashed) {
        return password_verify($plainInput, $storedOrNull);
    }
    // Compatibilidad: si aún guardaste en texto plano (temporalmente)
    return hash_equals($storedOrNull, $plainInput);
}

function maybe_rehash_password(mysqli $db, string $table, int $id, string $column, string $plain): void {
    // Si querés migrar poco a poco a hash cuando coincida
    $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost'=>11]);
    $stmt = $db->prepare("UPDATE {$table} SET {$column}=? WHERE id=? LIMIT 1");
    $stmt->bind_param('si', $hash, $id);
    $stmt->execute();
}
