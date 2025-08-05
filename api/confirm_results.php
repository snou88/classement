<?php
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$week = $input['week'];

try {
    $pdo->beginTransaction();
    
    // Récupérer tous les matchs terminés de la semaine
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE week = ? AND status = 'completed'");
    $stmt->execute([$week]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Trouver le meilleur et le pire score de la semaine
    $maxGoals = 0;
    $minGoals = PHP_INT_MAX;
    $bestTeamId = null;
    $worstTeamId = null;
    
    foreach ($matches as $match) {
        $totalGoals = $match['home_goals'] + $match['away_goals'];
        
        // Vérifier le meilleur score (BEST)
        if ($totalGoals > $maxGoals) {
            $maxGoals = $totalGoals;
            $bestTeamId = $match['home_goals'] > $match['away_goals'] ? $match['home_team_id'] : $match['away_team_id'];
        }
        
        // Vérifier le pire score (WORST)
        if ($totalGoals < $minGoals) {
            $minGoals = $totalGoals;
            $worstTeamId = $match['home_goals'] < $match['away_goals'] ? $match['home_team_id'] : $match['away_team_id'];
        }
    }
    
    foreach ($matches as $match) {
        $homeGoals = $match['home_goals'];
        $awayGoals = $match['away_goals'];
        $homeTeamId = $match['home_team_id'];
        $awayTeamId = $match['away_team_id'];
        
        // Calculer les points et statistiques
        $homePoints = 0;
        $awayPoints = 0;
        $homeWon = 0;
        $homeDrawn = 0;
        $homeLost = 0;
        $awayWon = 0;
        $awayDrawn = 0;
        $awayLost = 0;
        
        if ($homeGoals > $awayGoals) {
            $homePoints = 3;
            $homeWon = 1;
            $awayLost = 1;
        } elseif ($homeGoals < $awayGoals) {
            $awayPoints = 3;
            $awayWon = 1;
            $homeLost = 1;
        } else {
            $homePoints = 1;
            $awayPoints = 1;
            $homeDrawn = 1;
            $awayDrawn = 1;
        }
        
        // Vérifier si l'équipe à domicile a le meilleur ou le pire score
        $homeBest = ($homeTeamId == $bestTeamId) ? 1 : 0;
        $homeWorst = ($homeTeamId == $worstTeamId) ? 1 : 0;
        
        // Vérifier si l'équipe à l'extérieur a le meilleur ou le pire score
        $awayBest = ($awayTeamId == $bestTeamId) ? 1 : 0;
        $awayWorst = ($awayTeamId == $worstTeamId) ? 1 : 0;
        
        // Mettre à jour l'équipe domicile
        $stmt = $pdo->prepare("
            UPDATE teams SET 
                played = played + 1,
                won = won + ?,
                drawn = drawn + ?,
                lost = lost + ?,
                goals_for = goals_for + ?,
                goals_against = goals_against + ?,
                goal_difference = (goals_for + ?) - (goals_against + ?),
                best = best + ?,
                worst = worst + ?,
                points = points + ? + ? - ?
            WHERE id = ?
        ");
        $stmt->execute([
            $homeWon, $homeDrawn, $homeLost, 
            $homeGoals, $awayGoals,
            $homeGoals, $awayGoals, // Pour le calcul de la différence de buts
            $homeBest, $homeWorst,
            $homePoints, $homeBest, $homeWorst, // Points + bonus BEST - malus WORST
            $homeTeamId
        ]);
        
        // Mettre à jour l'équipe extérieure
        $stmt->execute([
            $awayWon, $awayDrawn, $awayLost, 
            $awayGoals, $homeGoals,
            $awayGoals, $homeGoals, // Pour le calcul de la différence de buts
            $awayBest, $awayWorst,
            $awayPoints, $awayBest, $awayWorst, // Points + bonus BEST - malus WORST
            $awayTeamId
        ]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Résultats confirmés et classement mis à jour avec les points BEST/WORST']);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>