<?php
echo "hellooooo";

class Block {
    function __construct($id, $timestamp, $data, $previousHash) {
        $this->id = $id;
        $this->timestamp = $timestamp;
        $this->data = $data;
        $this->previousHash = $previousHash;
        $this->hash = hash('sha256', $id . json_encode($data) . $previousHash);
    }
}

// Initialize blockchain with genesis block
$blockchain = [new Block(0, time(), ['genesis' => true], '0')];

// Database connection
$conn = new mysqli("localhost", "webapp_user", "h1123456789", "secureapp");

if ($conn->connect_error) {
    die("Error: " . $conn->connect_error);
}

// Query users
$result = $conn->query("SELECT id, username FROM users");
$users = $result->fetch_all(MYSQLI_ASSOC);

// Create new block
$lastBlock = end($blockchain);
$blockchain[] = new Block(
    count($blockchain),
    time(),
    [
        'query' => 'SELECT',
        'count' => count($users),
        'hash' => hash('sha256', json_encode($users))
    ],
    $lastBlock->hash
);

// Display results
echo "USERS:\n";
foreach ($users as $user) {
    echo "{$user['id']} [{$user['username']}]\n";
}

echo "\nBLOCKS:\n";
foreach ($blockchain as $block) {
    echo "#{$block->id} H:" . substr($block->hash, 0, 6) . " PH:" . substr($block->previousHash, 0, 6) . "\n";
}

$conn->close();
?>