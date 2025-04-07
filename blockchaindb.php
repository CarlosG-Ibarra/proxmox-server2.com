<?php

// 1. Conexión a la base de datos
$host = 'localhost';
$dbname = 'secureapp';
$user = 'webapp_user';
$pass = 'A123456789';

header('Content-Type: text/plain');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Obtener usuarios de la tabla 'users'
    $stmt = $pdo->query("SELECT username, email FROM users");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Clase Bloque para la estructura blockchain
    class Bloque {
        public $indice;
        public $timestamp;
        public $datos;
        public $hashAnterior;
        public $hash;
        
        public function __construct($indice, $timestamp, $datos, $hashAnterior = '') {
            $this->indice = $indice;
            $this->timestamp = $timestamp;
            $this->datos = $datos;
            $this->hashAnterior = $hashAnterior;
            $this->hash = $this->calcularHash();
        }
        
        public function calcularHash() {
            return hash('sha256', 
                $this->indice . 
                $this->timestamp . 
                json_encode($this->datos) . 
                $this->hashAnterior
            );
        }
    }

    // 4. Crear la cadena de bloques
    $blockchain = [];
    
    // Bloque génesis
    $blockchain[] = new Bloque(0, date('Y-m-d H:i:s'), [
        'mensaje' => 'Inicio del registro de usuarios',
        'sistema' => 'SecureApp v1.0'
    ], '0');

    // Añadir usuarios como bloques
    foreach ($usuarios as $usuario) {
        $ultimoBloque = end($blockchain);
        $blockchain[] = new Bloque(
            count($blockchain),
            date('Y-m-d H:i:s'),
            [
                'username' => $usuario['username'],
                'email' => $usuario['email']
            ],
            $ultimoBloque->hash
        );
    }

    // 5. Mostrar la blockchain
    echo "=== BLOCKCHAIN DE USUARIOS ===\n\n";
    echo "Total de bloques: " . count($blockchain) . "\n\n";

    foreach ($blockchain as $bloque) {
        echo "┌─────────────── BLOQUE #" . $bloque->indice . " ───────────────┐\n";
        echo "│ Timestamp: " . $bloque->timestamp . "\n";
        
        if ($bloque->indice === 0) {
            echo "│ " . $bloque->datos['mensaje'] . "\n";
            echo "│ Sistema: " . $bloque->datos['sistema'] . "\n";
        } else {
            echo "│ Usuario: " . $bloque->datos['username'] . "\n";
            echo "│ Email: " . $bloque->datos['email'] . "\n";
        }
        
        echo "├───────────────────────────────────────────────┤\n";
        echo "│ Hash anterior: " . substr($bloque->hashAnterior, 0, 10) . "...\n";
        echo "│ Hash actual: " . substr($bloque->hash, 0, 10) . "...\n";
        echo "└───────────────────────────────────────────────┘\n\n";
    }

    echo "=== FIN DE LA CADENA ===\n";
    echo "Bloques generados: " . (count($blockchain) - 1) . " usuarios\n";

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>