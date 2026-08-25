<?php
// --- CONFIG ---
$serviceAccountPath = __DIR__ . '/buscotec-firebase-adminsdk-fbsvc-cef5b6adb1.json';

// ⚠️ Después vamos a reemplazar esta variable con el token real del navegador
$targetToken = 'PEGAR_AQUI_EL_TOKEN_DEL_NAVEGADOR';

// Cargar credenciales
$serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
$privateKey = $serviceAccount['private_key'];
$clientEmail = $serviceAccount['client_email'];
$projectId   = $serviceAccount['project_id'];

// 1. Generar JWT
$header = base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT']));
$now = time();
$claims = [
  'iss' => $clientEmail,
  'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
  'aud' => 'https://oauth2.googleapis.com/token',
  'iat' => $now,
  'exp' => $now + 3600
];
$payload = base64_encode(json_encode($claims));
$unsigned = $header.'.'.$payload;
openssl_sign($unsigned, $signature, openssl_pkey_get_private($privateKey), 'sha256');
$jwt = $unsigned.'.'.base64_encode($signature);

// 2. Intercambiar JWT por access_token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query([
    'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion'=>$jwt
  ])
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$accessToken = $data['access_token'] ?? null;

if (!$accessToken) {
  die("❌ Error al obtener access_token: $response");
}

// 3. Enviar notificación
$message = [
  "message" => [
    "token" => $targetToken,
    "notification" => [
      "title" => "🚀 Hola desde Buscotec",
      "body" => "Prueba de notificación enviada desde PHP sin Composer"
    ]
  ]
];

$ch = curl_init("https://fcm.googleapis.com/v1/projects/$projectId/messages:send");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($message)
]);
$result = curl_exec($ch);
curl_close($ch);

echo "Respuesta FCM: ".$result;
