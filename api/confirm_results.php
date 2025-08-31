<?php
require_once 'config.php';

// On ne prend plus de filtre week : on traite **toutes** les semaines
// $weekFilter = isset($input['week']) ? (int)$input['week'] : null;

// Bonus/malus (à ajuster si besoin)
$BEST_BONUS = 1;
$WORST_MALUS = 1;

try {
    $pdo->beginTransaction();

    // Récupérer toutes les semaines à traiter (completed && not processed)
    $stmtWeeks = $pdo->query("SELECT DISTINCT week FROM matches WHERE status = 'completed' AND (processed = 0 OR processed IS NULL)");
    $weeks = $stmtWeeks->fetchAll(PDO::FETCH_COLUMN);
    if (empty($weeks)) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Aucune semaine à traiter.']);
        exit;
    }

    // Préparer la requête d'update (réutilisée)
    $stmtUpdateTeam = $pdo->prepare("
        UPDATE teams
        SET
            played = played + 1,
            won = won + ?,
            drawn = drawn + ?,
            lost = lost + ?,
            goals_for = goals_for + ?,
            goals_against = goals_against + ?,
            goal_difference = goal_difference + (? - ?),
            best = best + ?,
            worst = worst + ?,
            points = points + ? + ? - ?
        WHERE id = ?
    ");

    $stmtSelectMatches = $pdo->prepare("SELECT * FROM matches WHERE status = 'completed' AND (processed = 0 OR processed IS NULL) AND week = ?");
    $stmtMarkProcessed = $pdo->prepare("UPDATE matches SET processed = 1 WHERE id = ?");

    foreach ($weeks as $week) {
        // Récupérer tous les matchs de cette semaine non traités
        $stmtSelectMatches->execute([$week]);
        $matches = $stmtSelectMatches->fetchAll(PDO::FETCH_ASSOC);
        if (empty($matches)) continue;

        // Calcul des buts par équipe pour cette semaine (BEST/WORST)
        $teamGoals = [];
        foreach ($matches as $m) {
            $h = (int)$m['home_team_id'];
            $a = (int)$m['away_team_id'];
            $hg = (int)$m['home_goals'];
            $ag = (int)$m['away_goals'];

            if (!isset($teamGoals[$h])) $teamGoals[$h] = 0;
            if (!isset($teamGoals[$a])) $teamGoals[$a] = 0;

            $teamGoals[$h] += $hg;
            $teamGoals[$a] += $ag;
        }

        // Déterminer BEST / WORST pour la semaine
        $bestTeamIds = [];
        $worstTeamIds = [];
        if (!empty($teamGoals)) {
            $maxGoals = max($teamGoals);
            $minGoals = min($teamGoals);
            if ($maxGoals !== $minGoals) {
                foreach ($teamGoals as $teamId => $g) {
                    if ($g === $maxGoals) $bestTeamIds[] = (int)$teamId;
                    if ($g === $minGoals) $worstTeamIds[] = (int)$teamId;
                }
            }
        }

        // Mise à jour des équipes match par match
        foreach ($matches as $match) {
            $homeId = (int)$match['home_team_id'];
            $awayId = (int)$match['away_team_id'];
            $homeGoals = (int)$match['home_goals'];
            $awayGoals = (int)$match['away_goals'];

            // Résultat du match
            $homeWon = $homeDrawn = $homeLost = 0;
            $awayWon = $awayDrawn = $awayLost = 0;
            $homePoints = $awayPoints = 0;

            if ($homeGoals > $awayGoals) {
                $homePoints = 3; $homeWon = 1; $awayLost = 1;
            } elseif ($homeGoals < $awayGoals) {
                $awayPoints = 3; $awayWon = 1; $homeLost = 1;
            } else {
                $homePoints = 1; $awayPoints = 1; $homeDrawn = 1; $awayDrawn = 1;
            }

            // Bonus/malus pour la semaine
            $homeBest = in_array($homeId, $bestTeamIds, true) ? $BEST_BONUS : 0;
            $homeWorst = in_array($homeId, $worstTeamIds, true) ? $WORST_MALUS : 0;
            $awayBest = in_array($awayId, $bestTeamIds, true) ? $BEST_BONUS : 0;
            $awayWorst = in_array($awayId, $worstTeamIds, true) ? $WORST_MALUS : 0;

            // Update équipe domicile
            $stmtUpdateTeam->execute([
                $homeWon, $homeDrawn, $homeLost,
                $homeGoals, $awayGoals,
                $homeGoals, $awayGoals,
                $homeBest, $homeWorst,
                $homePoints, $homeBest, $homeWorst,
                $homeId
            ]);
            if ($stmtUpdateTeam->errorCode() !== '00000') {
                throw new Exception('Erreur update home: ' . implode(' | ', $stmtUpdateTeam->errorInfo()));
            }

            // Update équipe extérieure
            $stmtUpdateTeam->execute([
                $awayWon, $awayDrawn, $awayLost,
                $awayGoals, $homeGoals,
                $awayGoals, $homeGoals,
                $awayBest, $awayWorst,
                $awayPoints, $awayBest, $awayWorst,
                $awayId
            ]);
            if ($stmtUpdateTeam->errorCode() !== '00000') {
                throw new Exception('Erreur update away: ' . implode(' | ', $stmtUpdateTeam->errorInfo()));
            }

            // Marquer ce match comme traité
            $stmtMarkProcessed->execute([$match['id']]);
            if ($stmtMarkProcessed->errorCode() !== '00000') {
                throw new Exception('Erreur mark processed: ' . implode(' | ', $stmtMarkProcessed->errorInfo()));
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Toutes les semaines traitées et les équipes mises à jour.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
