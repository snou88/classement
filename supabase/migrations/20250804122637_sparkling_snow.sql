-- Script SQL pour créer la base de données
CREATE DATABASE IF NOT EXISTS football_league CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE football_league;

-- Table des équipes
CREATE TABLE IF NOT EXISTS teams (
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
);

-- Table des matchs
CREATE TABLE IF NOT EXISTS matches (
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
);

ALTER TABLE matches
  ADD COLUMN processed TINYINT(1) DEFAULT 0;

-- Insérer les équipes de Premier League
INSERT INTO teams (name) VALUES 
('Manchester City'),
('Arsenal'),
('Manchester United'),
('Newcastle United'),
('Liverpool'),
('Brighton'),
('Aston Villa'),
('Tottenham'),
('Brentford'),
('Fulham'),
('West Ham'),
('Crystal Palace'),
('Chelsea'),
('Bournemouth'),
('leeds United'),
('Burnley'),
('Sunderland'),
('Nottingham Forest'),
('Everton'),
('Wolves');

-- Réinitialiser les stats des équipes
UPDATE teams
SET 
  played = 0,
  won = 0,
  drawn = 0,
  lost = 0,
  goals_for = 0,
  goals_against = 0,
  goal_difference = 0,
  points = 0,
  best = 0,
  worst = 0;

-- Supprimer tous les matchs
DELETE FROM matches;

-- OU simplement réinitialiser les scores des matchs
UPDATE matches
SET 
  home_goals = NULL,
  away_goals = NULL,
  status = 'scheduled';