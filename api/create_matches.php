<?php
require_once 'config.php';
$input = json_decode(file_get_contents('php://input'), true);
$week = (int)$input['week'];

if (!empty($input['matches'])) {
    // Mode manuel : on récupère les rencontres envoyées
    $matches = $input['matches'];
} else {
    // Mode aléatoire (ancien) : générer automatiquement
    echo'';}

// Exemple d’insertion en base
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
      INSERT INTO matches (week, home_team_id, away_team_id)
      VALUES (:week, :home, :away)
    ");
    foreach ($matches as $m) {
        $stmt->execute([
            ':week' => $week,
            ':home' => $m['home_team'],
            ':away' => $m['away_team']
        ]);
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
