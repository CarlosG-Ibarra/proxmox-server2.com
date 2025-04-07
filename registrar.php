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
$auth0_domain = 'dev-disjapan7@fs6chap.us.auth0.com';
$client_id = 'vcvcX9qopsdnFTYJ4e1bVNnDkTZQaR0a';
$client_secret = 'V1jgWNU1Vx2gX-PKSUI1A8u19df3ejNeUd0cjjMF8fNFdhQQxd9r';
$connection = 'Username-Password-Authentication';

// 4. Get Auth0 Management API Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$auth0_domain/oauth/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'audience' => "https://$auth0_domain/api/v2/",
    'grant_type' => 'client_credentials'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
if ($response === false) {
    die("Error al obtener el token: " . curl_error($ch));
}

// 5. Process Auth0 response
$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    die("No se pudo obtener el token de acceso: " . $response);
}

$mgmt_token = $tokenData['access_token'];

// 6. Create Auth0 user
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$auth0_domain/api/v2/users");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password,
    'connection' => $connection,
    'user_metadata' => [
        'username' => $usuario
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $mgmt_token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 201) {
    $data = json_decode($response, true);
    $msg = $data['message'] ?? json_encode($data);
    die("Error creando usuario en Auth0: $msg");
}

// 7. Store in MariaDB
$conexion = new mysqli('localhost', "webapp_user", "A1123456789", "secureapp");

if($conexion->connect_error) {
    die("Conexión Fallida: " . $conexion->connect_error);
}

$stmt = $conexion->prepare('INSERT INTO users (username, email, password) VALUES (?,?,?)');
$stmt->bind_param("sss", $usuario, $email, $password);

if($stmt->execute()) {
    echo "Usuario registrado exitosamente en Auth0 y la base de datos<br>";
} else {
    echo "Error: ". $stmt->error;
}

$stmt->close();
$conexion->close();

echo "<br><a href='index.html'>Volver</a>";
?>