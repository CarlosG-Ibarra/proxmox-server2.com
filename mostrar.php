<?php
echo "Hola de mostrar<br>";
$conexion = new mysqli("localhost", "webapp_user", "A1123456789", "secureapp");

if ($conexion->connect_error) {
    echo "Fallo en coneccion<br>";
    die("Conexión fallida: " . $conexion->connect_error);
} else {
    echo "coneccion exitosa<br>";
}

$resultados = $conexion->query("SELECT * FROM users");

if ($resultados->num_rows > 0) {
    echo "<h2>Usuarios Registrados:</h2> <br>";
    while ($fila = $resultados->fetch_assoc()) {
        echo "ID: {$fila['id']} | Usuario: {$fila['username']} | Email: {$fila['email']}<br>";
    }
} else {
    echo "No hay usuarios registrados durmiendo a esta hora<br>";
}

$conexion->close();
echo "<br><a href='index.html'>Volver</a>";
?>