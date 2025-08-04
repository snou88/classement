<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT MAX(week) as max_week FROM matches");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $currentWeek = $result['max_week'] ? $result['max_week'] + 1 : 1;
    
    // Vérifier s'il y a des matchs non terminés pour la semaine précédente
    if ($result['max_week']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM matches WHERE week = ? AND status = 'scheduled'");
        $stmt->execute([$result['max_week']]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pending['pending'] > 0) {
            $currentWeek = $result['max_week'];
        }
    }
    
    echo json_encode(['success' => true, 'week' => $currentWeek]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>