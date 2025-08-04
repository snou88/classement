<?php
require_once 'config.php';

try {
    // Créer la table des équipes
    $sql = "CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        played INT DEFAULT 0,
        won INT DEFAULT 0,
        drawn INT DEFAULT 0,
        lost INT DEFAULT 0,
        goals_for INT DEFAULT 0,
        goals_against INT DEFAULT 0,
        goal_difference INT DEFAULT 0,
        points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Créer la table des matchs
    $sql = "CREATE TABLE IF NOT EXISTS matches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        week INT NOT NULL,
        home_team_id INT NOT NULL,
        away_team_id INT NOT NULL,
        home_goals INT NULL,
        away_goals INT NULL,
        status ENUM('scheduled', 'completed') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (home_team_id) REFERENCES teams(id),
        FOREIGN KEY (away_team_id) REFERENCES teams(id)
    )";
    $pdo->exec($sql);

    // Insérer les équipes de Premier League si elles n'existent pas
    $teams = [
        'Manchester City', 'Arsenal', 'Manchester United', 'Newcastle United',
        'Liverpool', 'Brighton', 'Aston Villa', 'Tottenham', 'Brentford',
        'Fulham', 'West Ham', 'Crystal Palace', 'Chelsea', 'Bournemouth',
        'Sheffield United', 'Burnley', 'Luton Town', 'Nottingham Forest',
        'Everton', 'Wolves'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO teams (name) VALUES (?)");
    foreach ($teams as $team) {
        $stmt->execute([$team]);
    }

    echo json_encode(['success' => true, 'message' => 'Base de données initialisée avec succès']);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>