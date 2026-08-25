<?php
/**
 * backend/config/allowlist.php
 * Lista blanca de emails autorizados para acceder al panel Admin.
 * IMPORTANTE: mantener TODO en minúsculas.
 */

return [
    // Activar o desactivar la verificación de allowlist (útil en desarrollo)
    'enforce' => true,

    // Emails permitidos (siempre en minúsculas)
    'emails' => [
        'oscarns@gmail.com',
        'orticelli@gmail.com',
    ],
];
