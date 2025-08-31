<?php
// Connexion à la base MySQL
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "football_league"; // change par le nom de ta base

$conn = new mysqli($host, $user, $pass, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Réinitialiser les stats des équipes
$sql_reset_teams = "
    UPDATE teams SET 
        played = 0, 
        won = 0, 
        drawn = 0, 
        lost = 0, 
        goals_for = 0, 
        goals_against = 0, 
        goal_difference = 0, 
        points = 0, 
        best = 0, 
        worst = 0
";
$conn->query($sql_reset_teams);

// Supprimer tous les matchs
// $conn->query("DELETE FROM matches");

// OU réinitialiser seulement les scores
$conn->query("
DELETE FROM matches;
");

$conn->close();

// Redirection vers index.html
header("Location: ../admin.html");
exit;
