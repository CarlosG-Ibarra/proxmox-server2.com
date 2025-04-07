<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// 1. Get login credentials
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// 2. Validate input
if (empty($username) || empty($password)) {
    die("Por favor ingrese nombre de usuario y contraseña");
}

// 3. Auth0 Configuration
$auth0_domain = 'dev-4bipmsm70fe6chqp.us.auth0.com';
$client_id = 'VcVcX9Oqps4nFTYJ4el8vRmDKR7QaRO0';
$client_secret = 'V1jgXAUiVSz9gX-PkSUIlASu19df3ejNeUdoCjjNP8fWFdM0Qxq9mqqoSofqF13L';

// 4. Authenticate with Auth0
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://$auth0_domain/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'password',
        'username' => $username,
        'password' => $password,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'audience' => "https://$auth0_domain/api/v2/",
        'scope' => 'openid profile email'
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
    die("Error de autenticación: Credenciales inválidas");
}

$authData = json_decode($response, true);

// 6. Verify user in MariaDB
try {
    $conexion = new mysqli('localhost', "webapp_user", "A!123456789", "secureapp");
    
    if ($conexion->connect_error) {
        throw new Exception("Error de conexión a la base de datos");
    }

    $stmt = $conexion->prepare("SELECT id, username, email, password FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Usuario no encontrado en la base de datos local");
    }

    $user = $result->fetch_assoc();
    
    // Verify password (if you want to double-check against local DB)
    if (!password_verify($password, $user['password'])) {
        throw new Exception("Contraseña local no coincide");
    }

    // 7. Create user session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['auth0_token'] = $authData['access_token'];
    $_SESSION['id_token'] = $authData['id_token'];
    
    // Set cookie with secure flags
    setcookie('auth_token', $authData['access_token'], [
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    // 8. Successful login response
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    die("Error en el sistema de autenticación. Por favor intente nuevamente.");
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conexion)) $conexion->close();
}
?>