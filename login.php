<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// 1. Get login credentials
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// 2. Validate input
if (empty($email) || empty($password)) {
    header("Location: login.html?error=" . urlencode("Por favor ingrese email y contraseña"));
    exit();
}

// 3. Auth0 Configuration
$auth0_domain = 'dev-4bipmsm70fe6chqp.us.auth0.com';
$client_id = 'VcVcX9Oqps4nFTYJ4el8vRmDKR7QaRO0';
$client_secret = 'V1jgXAUiVSz9gX-PkSUIlASu19df3ejNeUdoCjjNP8fWFdM0Qxq9mqqoSofqF13L';
$realm = 'Username-Password-Authentication';

// 4. Authenticate with Auth0
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://$auth0_domain/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'http://auth0.com/oauth/grant-type/password-realm',
        'username' => $email,
        'password' => $password,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'audience' => "https://$auth0_domain/api/v2/",
        'scope' => 'openid profile email',
        'realm' => $realm
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Process Auth0 response
if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error_description'] ?? 'Credenciales inválidas';
    header("Location: login.html?error=" . urlencode($errorMsg));
    exit();
}

$authData = json_decode($response, true);

// 6. Store session data
$_SESSION['user_email'] = $email;
$_SESSION['auth0_token'] = $authData['access_token'];
$_SESSION['id_token'] = $authData['id_token'];

// 7. Show success message
echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de sesión exitoso</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        .success-container {
            background: white;
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-icon {
            color: #4CAF50;
            font-size: 72px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>¡Inicio de sesión exitoso!</h1>
        <p>Has iniciado sesión correctamente como '.htmlspecialchars($email).'</p>
        <a href="login.html" class="btn">Regresar</a>
    </div>
</body>
</html>';

exit();
?>