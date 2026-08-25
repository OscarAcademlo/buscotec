<?php
// get_token.php — Generar token FCM desde el backend con JSON

header('Content-Type: application/json');

$serviceAccountPath = __DIR__ . '/buscotec-firebase-adminsdk-fbsvc-cef5b6adb1.json';

$serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
$privateKey = $serviceAccount['private_key'];
$clientEmail = $serviceAccount['client_email'];
$projectId   = $serviceAccount['project_id'];

// 1. Crear JWT
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

// 2. Intercambiar JWT por Access Token
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

if (!isset($data['access_token'])) {
  http_response_code(500);
  echo json_encode(["error" => "No se pudo generar access_token", "response" => $response]);
  exit;
}

// Responder al navegador con el access_token
echo json_encode(["access_token" => $data['access_token']]);
