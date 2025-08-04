<?php
require_once 'config.php';

$week = $_GET['week'] ?? 1;

try {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE week = ? ORDER BY id");
    $stmt->execute([$week]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'matches' => $matches]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>