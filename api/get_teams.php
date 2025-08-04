<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT * FROM teams ORDER BY points DESC, goal_difference DESC, goals_for DESC");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'teams' => $teams]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>