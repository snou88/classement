// Configuration
const API_BASE = 'api/';

// Données des équipes Premier League
const teams = [
    { id: 1, name: 'Manchester City', logo: 'https://upload.wikimedia.org/wikipedia/en/e/eb/Manchester_City_FC_badge.svg' },
    { id: 2, name: 'Arsenal', logo: 'https://upload.wikimedia.org/wikipedia/en/5/53/Arsenal_FC.svg' },
    { id: 3, name: 'Manchester United', logo: 'https://upload.wikimedia.org/wikipedia/en/7/7a/Manchester_United_FC_crest.svg' },
    { id: 4, name: 'Newcastle United', logo: 'https://upload.wikimedia.org/wikipedia/en/5/56/Newcastle_United_Logo.svg' },
    { id: 5, name: 'Liverpool', logo: 'https://upload.wikimedia.org/wikipedia/en/0/0c/Liverpool_FC.svg' },
    { id: 6, name: 'Brighton', logo: 'https://upload.wikimedia.org/wikipedia/en/f/fd/Brighton_%26_Hove_Albion_logo.svg' },
    { id: 7, name: 'Aston Villa', logo: 'https://upload.wikimedia.org/wikipedia/en/9/9f/Aston_Villa_logo.svg' },
    { id: 8, name: 'Tottenham', logo: 'https://upload.wikimedia.org/wikipedia/en/b/b4/Tottenham_Hotspur.svg' },
    { id: 9, name: 'Brentford', logo: 'https://upload.wikimedia.org/wikipedia/en/2/2a/Brentford_FC_crest.svg' },
    { id: 10, name: 'Fulham', logo: 'https://upload.wikimedia.org/wikipedia/en/e/eb/Fulham_FC_(shield).svg' },
    { id: 11, name: 'West Ham', logo: 'https://upload.wikimedia.org/wikipedia/en/c/c2/West_Ham_United_FC_logo.svg' },
    { id: 12, name: 'Crystal Palace', logo: 'https://upload.wikimedia.org/wikipedia/en/0/0c/Crystal_Palace_FC_logo.svg' },
    { id: 13, name: 'Chelsea', logo: 'https://upload.wikimedia.org/wikipedia/en/c/cc/Chelsea_FC.svg' },
    { id: 14, name: 'Bournemouth', logo: 'https://upload.wikimedia.org/wikipedia/en/e/e5/AFC_Bournemouth.svg' },
    { id: 15, name: 'Leeds United', logo: 'https://upload.wikimedia.org/wikipedia/en/0/0c/Leeds_United_Logo.svg' },
    { id: 16, name: 'Burnley', logo: 'https://upload.wikimedia.org/wikipedia/en/0/02/Burnley_FC_badge.svg' },
    { id: 17, name: 'Sheffield United', logo: 'https://upload.wikimedia.org/wikipedia/en/f/f2/Sheffield_United_FC_logo.svg' },
    { id: 18, name: 'Nottingham Forest', logo: 'https://upload.wikimedia.org/wikipedia/en/5/5c/Nottingham_Forest_F.C._logo.svg' },
    { id: 19, name: 'Everton', logo: 'https://upload.wikimedia.org/wikipedia/en/7/7c/Everton_FC_logo.svg' },
    { id: 20, name: 'Wolves', logo: 'https://upload.wikimedia.org/wikipedia/en/f/fc/Wolverhampton_Wanderers.svg' }
];

// Fonction pour charger le classement
async function loadLeagueTable() {
    try {
        const response = await fetch(API_BASE + 'get_teams.php');
        const data = await response.json();

        if (data.success) {
            displayLeagueTable(data.teams);
        } else {
            console.error('Erreur:', data.message);
            // Afficher un tableau vide si pas de données
            displayLeagueTable([]);
        }
    } catch (error) {
        console.error('Erreur de connexion:', error);
        // Afficher un tableau vide en cas d'erreur
        displayLeagueTable([]);
    }
}

function displayLeagueTable(teamsData) {
    const tableBody = document.getElementById('tableBody');

    if (!teamsData || teamsData.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 2rem; color: #666;">
                    Aucune donnée disponible. Utilisez l'interface admin pour initialiser les équipes.
                </td>
            </tr>
        `;
        return;
    }

    // Trier les équipes
    teamsData.sort((a, b) => {
        if (b.points !== a.points) return b.points - a.points;
        if (b.goal_difference !== a.goal_difference) return b.goal_difference - a.goal_difference;
        return b.goals_for - a.goals_for;
    });

    tableBody.innerHTML = '';

    teamsData.forEach((team, index) => {
        const position = index + 1;
        let qualificationClass = '';

        if (position <= 4) qualificationClass = 'champions';
        else if (position <= 6) qualificationClass = 'europa';
        else if (position >= 18) qualificationClass = 'relegation';

        const row = document.createElement('tr');
        row.className = qualificationClass;

        // ✅ Utiliser le logo depuis la base (logo_url)
        const logo = team.logo_url || 'https://images.pexels.com/photos/46798/the-ball-stadion-football-the-pitch-46798.jpeg?auto=compress&cs=tinysrgb&w=64&h=64&dpr=1';

        row.innerHTML = `
            <td><span class="position">${position}</span></td>
            <td>
                <div class="team-info">
                    <img src="${logo}" alt="${team.name}" class="team-logo">
                    <span>${team.name}</span>
                </div>
            </td>
            <td>${team.played}</td>
            <td>${team.won}</td>
            <td>${team.drawn}</td>
            <td>${team.lost}</td>
            <td>${team.goals_for}</td>
            <td>${team.goals_against}</td>
            <td style="color: ${team.goal_difference > 0 ? '#00ff87' : team.goal_difference < 0 ? '#ff3838' : '#333'}">
                ${team.goal_difference > 0 ? '+' : ''}${team.goal_difference}
            </td>
            <td><span class="points">${team.points}</span></td>
        `;

        tableBody.appendChild(row);
    });
}

// Charger le classement au chargement de la page
document.addEventListener('DOMContentLoaded', function () {
    loadLeagueTable();

    // Actualiser toutes les 30 secondes
    setInterval(loadLeagueTable, 30000);
});