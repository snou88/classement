<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get current week
    $stmt = $pdo->query("SELECT MAX(week) as max_week FROM matches");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxWeek = $result['max_week'] ?: 1;
    
    // Get requested week (default: current week)
    $week = isset($_GET['week']) ? (int)$_GET['week'] : $maxWeek;
    
    // Get matches for the requested week
    $stmt = $pdo->prepare("
        SELECT 
            m.*, 
            ht.name as home_team_name,
            at.name as away_team_name,
            ht.logo_url as home_team_logo,
            at.logo_url as away_team_logo
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        WHERE m.week = ? AND m.status = 'completed'
        ORDER BY m.id ASC
    ");
    $stmt->execute([$week]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate best and worst performances based on goals scored
    $bestPerformance = [
        'team_id' => null,
        'team_name' => '',
        'team_logo' => '',
        'goals_scored' => 0,
        'matches' => 0
    ];
    
    $worstPerformance = [
        'team_id' => null,
        'team_name' => '',
        'team_logo' => '',
        'goals_scored' => PHP_INT_MAX,
        'matches' => 0
    ];
    
    // Track team performances
    $teamPerformance = [];
    
    foreach ($matches as $match) {
        $homeId = $match['home_team_id'];
        $awayId = $match['away_team_id'];
        
        // Initialize if needed
        if (!isset($teamPerformance[$homeId])) {
            $teamPerformance[$homeId] = [
                'name' => $match['home_team_name'],
                'logo' => $match['home_team_logo'],
                'goals_scored' => 0,
                'matches' => 0
            ];
        }
        
        if (!isset($teamPerformance[$awayId])) {
            $teamPerformance[$awayId] = [
                'name' => $match['away_team_name'],
                'logo' => $match['away_team_logo'],
                'goals_scored' => 0,
                'matches' => 0
            ];
        }
        
        // Update statistics
        $teamPerformance[$homeId]['goals_scored'] += $match['home_goals'];
        $teamPerformance[$homeId]['matches']++;
        
        $teamPerformance[$awayId]['goals_scored'] += $match['away_goals'];
        $teamPerformance[$awayId]['matches']++;
    }
    
    // Find best and worst performances
    foreach ($teamPerformance as $teamId => $stats) {
        if ($stats['matches'] > 0) {
            // Best performance (most goals scored)
            if ($stats['goals_scored'] > $bestPerformance['goals_scored']) {
                $bestPerformance = [
                    'team_id' => $teamId,
                    'team_name' => $stats['name'],
                    'team_logo' => $stats['logo'],
                    'goals_scored' => $stats['goals_scored'],
                    'matches' => $stats['matches']
                ];
            }
            
            // Worst performance (fewest goals scored, but only if they've played at least one match)
            if ($stats['goals_scored'] < $worstPerformance['goals_scored']) {
                $worstPerformance = [
                    'team_id' => $teamId,
                    'team_name' => $stats['name'],
                    'team_logo' => $stats['logo'],
                    'goals_scored' => $stats['goals_scored'],
                    'matches' => $stats['matches']
                ];
            }
        }
    }
    
    // If no team scored (all zeros), don't show worst performance
    if ($worstPerformance['goals_scored'] === PHP_INT_MAX) {
        $worstPerformance = null;
    }
    
    // Get list of available weeks
    $stmt = $pdo->query("SELECT DISTINCT week FROM matches WHERE status = 'completed' ORDER BY week");
    $availableWeeks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'current_week' => $week,
        'max_week' => $maxWeek,
        'available_weeks' => $availableWeeks,
        'matches' => $matches,
        'best_performance' => $bestPerformance['team_id'] !== null ? $bestPerformance : null,
        'worst_performance' => $worstPerformance
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
