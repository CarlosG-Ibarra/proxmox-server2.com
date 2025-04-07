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
$realm = 'Username-Password-Authentication'; // Your Auth0 connection name

// 4. Authenticate with Auth0 using Password Realm grant
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://$auth0_domain/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'http://auth0.com/oauth/grant-type/password-realm',
        'username' => $email, // Using email as username
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

// 6. Create user session with Auth0 data
$_SESSION['user_email'] = $email;
$_SESSION['auth0_token'] = $authData['access_token'];
$_SESSION['id_token'] = $authData['id_token'];

// Get user profile from ID token
$tokenParts = explode('.', $authData['id_token']);
$tokenPayload = base64_decode($tokenParts[1]);
$userData = json_decode($tokenPayload, true);

// Store relevant user data in session
$_SESSION['user_name'] = $userData['name'] ?? '';
$_SESSION['user_picture'] = $userData['picture'] ?? '';

// 7. Set secure cookie
setcookie('auth_token', $authData['access_token'], [
    'expires' => time() + 3600,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// 8. Successful login redirect
header("Location: dashboard.php");
exit();
?>