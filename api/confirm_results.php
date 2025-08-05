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

    // Étape 1 : Calcul des buts par équipe
    $teamGoals = [];

    foreach ($matches as $match) {
        $homeTeamId = $match['home_team_id'];
        $awayTeamId = $match['away_team_id'];
        $homeGoals = $match['home_goals'];
        $awayGoals = $match['away_goals'];

        if (!isset($teamGoals[$homeTeamId])) {
            $teamGoals[$homeTeamId] = 0;
        }
        if (!isset($teamGoals[$awayTeamId])) {
            $teamGoals[$awayTeamId] = 0;
        }

        $teamGoals[$homeTeamId] += $homeGoals;
        $teamGoals[$awayTeamId] += $awayGoals;
    }

    // Étape 2 : Déterminer les équipes BEST et WORST
    $bestTeamIds = [];
    $worstTeamIds = [];

    if (!empty($teamGoals)) {
        $maxGoals = max($teamGoals);
        $minGoals = min($teamGoals);

        if ($maxGoals !== $minGoals) {
            foreach ($teamGoals as $teamId => $goals) {
                if ($goals === $maxGoals) {
                    $bestTeamIds[] = $teamId;
                }
                if ($goals === $minGoals) {
                    $worstTeamIds[] = $teamId;
                }
            }
        }
    }

    // Étape 3 : Mise à jour des stats et points
    foreach ($matches as $match) {
        $homeGoals = $match['home_goals'];
        $awayGoals = $match['away_goals'];
        $homeTeamId = $match['home_team_id'];
        $awayTeamId = $match['away_team_id'];

        // Résultat du match
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

        // Bonus/malus BEST/WORST
        $homeBest = in_array($homeTeamId, $bestTeamIds) ? 1 : 0;
        $homeWorst = in_array($homeTeamId, $worstTeamIds) ? 1 : 0;
        $awayBest = in_array($awayTeamId, $bestTeamIds) ? 1 : 0;
        $awayWorst = in_array($awayTeamId, $worstTeamIds) ? 1 : 0;

        $homeMatchPoints = $homePoints;
        $awayMatchPoints = $awayPoints;

        // Stats équipe à domicile
        $stmtCurrent = $pdo->prepare("SELECT goals_for, goals_against FROM teams WHERE id = ?");
        $stmtCurrent->execute([$homeTeamId]);
        $currentHome = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        $newHomeGoalsFor = $currentHome['goals_for'] + $homeGoals;
        $newHomeGoalsAgainst = $currentHome['goals_against'] + $awayGoals;
        $newHomeGoalDifference = $newHomeGoalsFor - $newHomeGoalsAgainst;

        // Mise à jour équipe domicile
        $stmt = $pdo->prepare("
            UPDATE teams 
            SET 
                played = played + 1,
                won = won + ?,
                drawn = drawn + ?,
                lost = lost + ?,
                goals_for = ?,
                goals_against = ?,
                goal_difference = ?,
                best = best + ?,
                worst = worst + ?,
                points = points + ? + ? - ?
            WHERE id = ?
        ");
        $stmt->execute([
            $homeWon, $homeDrawn, $homeLost,
            $newHomeGoalsFor, $newHomeGoalsAgainst, $newHomeGoalDifference,
            $homeBest, $homeWorst,
            $homeMatchPoints, $homeBest, $homeWorst,
            $homeTeamId
        ]);

        // Stats équipe à l'extérieur
        $stmtCurrent->execute([$awayTeamId]);
        $currentAway = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        $newAwayGoalsFor = $currentAway['goals_for'] + $awayGoals;
        $newAwayGoalsAgainst = $currentAway['goals_against'] + $homeGoals;
        $newAwayGoalDifference = $newAwayGoalsFor - $newAwayGoalsAgainst;

        // Mise à jour équipe extérieure
        $stmt = $pdo->prepare("
            UPDATE teams 
            SET 
                played = played + 1,
                won = won + ?,
                drawn = drawn + ?,
                lost = lost + ?,
                goals_for = ?,
                goals_against = ?,
                goal_difference = ?,
                best = best + ?,
                worst = worst + ?,
                points = points + ? + ? - ?
            WHERE id = ?
        ");
        $stmt->execute([
            $awayWon, $awayDrawn, $awayLost,
            $newAwayGoalsFor, $newAwayGoalsAgainst, $newAwayGoalDifference,
            $awayBest, $awayWorst,
            $awayMatchPoints, $awayBest, $awayWorst,
            $awayTeamId
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Résultats confirmés et classement mis à jour avec les points BEST/WORST']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}