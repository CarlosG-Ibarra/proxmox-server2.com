<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Get form data
$usuario = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// 2. Validate input
if(empty($usuario) || empty($email) || empty($password)) {
    die("Faltan datos del formulario");
}

// 3. Auth0 Configuration
$auth0_domain = 'dev-4bipmsm70fe6chqp.us.auth0.com';
$client_id = 'VcVcX9Oqps4nFTYJ4el8vRmDKR7QaRO0';
$client_secret = 'V1jgXAUiVSz9gX-PkSUIlASu19df3ejNeUdoCjjNP8fWFdM0Qxq9mqqoSofqF13L';
$connection = 'Username-Password-Authentication';

// 4. Get Auth0 Management API Token with error handling
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://$auth0_domain/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'audience' => "https://$auth0_domain/api/v2/",
        'grant_type' => 'client_credentials'
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_SSL_VERIFYPEER => true // Keep this true for production
]);

$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($tokenResponse === false) {
    die("Error al obtener el token: " . curl_error($ch));
}

curl_close($ch);

// 5. Process Auth0 token response with better validation
$tokenData = json_decode($tokenResponse, true);

if (!isset($tokenData['access_token'])) {
    error_log("Auth0 Token Error Response: " . print_r($tokenData, true));
    die("Failed to get valid access token. HTTP Code: $tokenHttpCode");
}

$mgmt_token = $tokenData['access_token'];

// 6. Create Auth0 user with enhanced error handling
$userData = [
    'email' => $email,
    'password' => $password,
    'connection' => $connection,
    'user_metadata' => [
        'username' => $usuario
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://$auth0_domain/api/v2/users",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($userData),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $mgmt_token",
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 201) {
    $data = json_decode($response, true);
    error_log("Auth0 User Creation Error: " . print_r($data, true));
    die("Error creando usuario en Auth0 (Code $httpcode): " . 
        ($data['message'] ?? 'Unknown error'));
}

// 7. Store in MariaDB with transaction handling
$dbSuccess = false;
$conexion = null;
$stmt = null;

try {
    // Connect to MariaDB
    $conexion = new mysqli('localhost', "webapp_user", "A1123456789", "secureapp");
    
    if($conexion->connect_error) {
        throw new Exception("Conexión Fallida: " . $conexion->connect_error);
    }
    
    // Start transaction
    $conexion->autocommit(false);
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    if ($hashedPassword === false) {
        throw new Exception("Error al hashear la contraseña");
    }
    
    // Prepare statement
    $stmt = $conexion->prepare('INSERT INTO users (username, email, password) VALUES (?,?,?)');
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }
    
    // Bind parameters
    if (!$stmt->bind_param("sss", $usuario, $email, $hashedPassword)) {
        throw new Exception("Error al vincular parámetros: " . $stmt->error);
    }
    
    // Execute
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    
    // Commit transaction
    $conexion->commit();
    $dbSuccess = true;
    
} catch (Exception $e) {
    // Rollback on error
    if ($conexion) {
        $conexion->rollback();
    }
    error_log("Database Error: " . $e->getMessage());
    $dbSuccess = false;
} finally {
    // Clean up resources
    if ($stmt) $stmt->close();
    if ($conexion) $conexion->close();
}

// Output results
if ($dbSuccess) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Registro Completo</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .success { color: #4CAF50; font-size: 24px; margin-bottom: 20px; }
            .error { color: #f44336; }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='success'>✓ Registro exitoso</div>
        <p>Usuario creado en Auth0 y en la base de datos local.</p>
        <a href='index.html' class='btn'>Volver al inicio</a>
    </body>
    </html>";
} else {
    echo "<div class='error'>Error: Usuario creado en Auth0 pero no en la base de datos local.</div>";
    error_log("Partial success: User created in Auth0 but not in MariaDB");
}

exit();
?>