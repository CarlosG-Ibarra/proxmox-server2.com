<?php
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

// 7. Store in MariaDB with password hashing
$conexion = new mysqli('localhost', "webapp_user", "A1123456789", "secureapp");

if($conexion->connect_error) {
    die("Conexión Fallida: " . $conexion->connect_error);
}

// Hash password before storing
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conexion->prepare('INSERT INTO users (username, email, password) VALUES (?,?,?)');
$stmt->bind_param("sss", $usuario, $email, $hashedPassword);

if($stmt->execute()) {
    echo "Usuario registrado exitosamente en Auth0 y la base de datos<br>";
} else {
    echo "Error: ". $stmt->error;
}

$stmt->close();
$conexion->close();

echo "<br><a href='index.html'>Volver</a>";
?>