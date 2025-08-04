<?php
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$matchId = $input['match_id'];
$homeGoals = $input['home_goals'];
$awayGoals = $input['away_goals'];

try {
    $stmt = $pdo->prepare("UPDATE matches SET home_goals = ?, away_goals = ?, status = 'completed' WHERE id = ?");
    $stmt->execute([$homeGoals, $awayGoals, $matchId]);
    
    echo json_encode(['success' => true, 'message' => 'Résultat mis à jour']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>