// Configuration
const API_BASE = 'api/';

let currentWeek = 1;
let weekMatches = [];
let teams = [];

// Fonction pour changer d'onglet
function showTab(tabName) {
    // Masquer tous les onglets
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });

    // Désactiver tous les boutons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    // Activer l'onglet sélectionné
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');

    if (tabName === 'results') {
        loadMatchResults();
    }
}

// Fonction pour obtenir la semaine courante
async function getCurrentWeek() {
    try {
        const response = await fetch(API_BASE + 'get_current_week.php');
        const data = await response.json();

        if (data.success) {
            currentWeek = data.week;
            document.getElementById('currentWeek').textContent = currentWeek;
            document.getElementById('resultsWeek').textContent = currentWeek;
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

const nbMatches = 10;   // nombre de rencontres à générer

async function loadTeams() {
    const res = await fetch(API_BASE + 'get_teams.php');
    const data = await res.json();

    if (!data.success) {
        alert('Erreur au chargement des équipes : ' + data.message);
        return;
    }

    // On récupère bien le tableau JSON["teams"]
    teams = data.teams;
    buildManualForm();
}

function buildManualForm() {
    const container = document.getElementById('matchesContainer');
    container.innerHTML = '';

    for (let i = 1; i <= nbMatches; i++) {
        const options = teams
            .map(t => `<option value="${t.id}">${t.name}</option>`)
            .join('');

        const row = document.createElement('div');
        row.classList.add('match-row');
        row.innerHTML = `
        <label>Match ${i} :</label>
        <select name="home_team_${i}" required>
          <option value="">-- Domicile --</option>
          ${options}
        </select>
        <span>vs</span>
        <select name="away_team_${i}" required>
          <option value="">-- Extérieur --</option>
          ${options}
        </select>
      `;
        container.appendChild(row);
    }
}

document.addEventListener('DOMContentLoaded', loadTeams);

// Fonction pour charger les rencontres de la semaine
async function loadWeekMatches() {
    try {
        const response = await fetch(API_BASE + 'get_matches.php?week=' + currentWeek);
        const data = await response.json();

        if (data.success) {
            weekMatches = data.matches;
            displayWeekMatches();
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

// Fonction pour afficher les rencontres
function displayWeekMatches() {
    const container = document.getElementById('weekMatches');

    if (weekMatches.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #666;">Aucune rencontre créée pour cette semaine.</p>';
        return;
    }

    container.innerHTML = '';

    weekMatches.forEach(match => {
        const homeTeam = teams.find(t => t.id == match.home_team_id);
        const awayTeam = teams.find(t => t.id == match.away_team_id);

        const matchCard = document.createElement('div');
        matchCard.className = 'match-card';

        matchCard.innerHTML = `
            <div class="match-teams">
                <div class="team">
                    <img src="${homeTeam.logo}" alt="${homeTeam.name}" class="team-logo">
                    <span>${homeTeam.name}</span>
                </div>
                <span class="vs">VS</span>
                <div class="team">
                    <span>${awayTeam.name}</span>
                    <img src="${awayTeam.logo}" alt="${awayTeam.name}" class="team-logo">
                </div>
            </div>
        `;

        container.appendChild(matchCard);
    });
}

// Fonction pour charger les résultats
async function loadMatchResults() {
    try {
        const response = await fetch(API_BASE + 'get_matches.php?week=' + currentWeek);
        const data = await response.json();

        if (data.success) {
            weekMatches = data.matches;
            displayMatchResults();
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

// Fonction pour afficher les formulaires de résultats
function displayMatchResults() {
    const container = document.getElementById('matchResults');
    const confirmBtn = document.getElementById('confirmResultsBtn');

    if (weekMatches.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #666;">Aucune rencontre disponible pour cette semaine.</p>';
        confirmBtn.style.display = 'none';
        return;
    }

    container.innerHTML = '';
    let hasResults = false;

    weekMatches.forEach(match => {
        const homeTeam = teams.find(t => t.id == match.home_team_id);
        const awayTeam = teams.find(t => t.id == match.away_team_id);

        const resultCard = document.createElement('div');
        resultCard.className = 'result-card';
        if (match.status === 'completed') {
            resultCard.classList.add('completed');
            hasResults = true;
        }

        resultCard.innerHTML = `
            <div class="result-form">
                <div class="team">
                    <img src="${homeTeam.logo}" alt="${homeTeam.name}" class="team-logo">
                    <span>${homeTeam.name}</span>
                </div>
                <input type="number" min="0" max="10" class="score-input" 
                       value="${match.home_goals || ''}" 
                       onchange="updateMatchResult(${match.id}, this.value, document.querySelector('[data-away-${match.id}]').value)">
                <span class="score-separator">-</span>
                <input type="number" min="0" max="10" class="score-input" 
                       value="${match.away_goals || ''}" 
                       data-away-${match.id}
                       onchange="updateMatchResult(${match.id}, document.querySelector('[data-home-${match.id}]').value, this.value)">
                <div class="team">
                    <span>${awayTeam.name}</span>
                    <img src="${awayTeam.logo}" alt="${awayTeam.name}" class="team-logo">
                </div>
            </div>
            ${match.status === 'completed' ?
                '<div class="result-status completed">✓ Résultat enregistré</div>' :
                ''
            }
        `;

        // Ajouter l'attribut data-home après création
        const homeInput = resultCard.querySelector('.score-input');
        homeInput.setAttribute('data-home-' + match.id, '');

        container.appendChild(resultCard);
    });

    confirmBtn.style.display = hasResults ? 'block' : 'none';
}

// Fonction pour mettre à jour un résultat de match
async function updateMatchResult(matchId, homeGoals, awayGoals) {
    if (homeGoals === '' || awayGoals === '') return;

    try {
        const response = await fetch(API_BASE + 'update_match_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                match_id: matchId,
                home_goals: parseInt(homeGoals),
                away_goals: parseInt(awayGoals)
            })
        });

        const data = await response.json();

        if (data.success) {
            // Recharger les résultats pour mettre à jour l'affichage
            await loadMatchResults();
        } else {
            alert('Erreur: ' + data.message);
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

// Fonction pour confirmer tous les résultats
async function confirmAllResults() {
    if (!confirm('Êtes-vous sûr de vouloir confirmer tous les résultats ? Cette action mettra à jour le classement.')) {
        return;
    }

    const btn = document.getElementById('confirmResultsBtn');
    btn.disabled = true;
    btn.textContent = 'Confirmation en cours...';

    try {
        const response = await fetch(API_BASE + 'confirm_results.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ week: currentWeek })
        });

        const data = await response.json();

        if (data.success) {
            alert('Résultats confirmés ! Le classement a été mis à jour.');
            // Passer à la semaine suivante
            currentWeek++;
            document.getElementById('currentWeek').textContent = currentWeek;
            document.getElementById('resultsWeek').textContent = currentWeek;

            // Réinitialiser l'interface
            document.getElementById('createMatchesBtn').disabled = false;
            document.getElementById('createMatchesBtn').textContent = 'Créer les rencontres de la semaine';
            document.getElementById('weekMatches').innerHTML = '';
            document.getElementById('matchResults').innerHTML = '';
            btn.style.display = 'none';

            // Retourner à l'onglet création
            showTab('matches');
            document.querySelector('.tab-btn').click();
        } else {
            alert('Erreur: ' + data.message);
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur de connexion');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirmer tous les résultats';
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function () {
    loadTeams();
    getCurrentWeek();
    loadWeekMatches();
});

document.getElementById('manualMatchesForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const matches = [];

    // Pour chaque ligne i, on récupère home et away
    for (let i = 1; i <= nbMatches; i++) {
        const homeId = form[`home_team_${i}`].value;
        const awayId = form[`away_team_${i}`].value;
        if (homeId === awayId) {
            alert(`Le match ${i} a la même équipe à domicile et à l'extérieur !`);
            return;
        }
        matches.push({ home_team: homeId, away_team: awayId });
    }

    // Désactiver le bouton pour éviter les doubles clics
    const btn = document.getElementById('submitManualMatchesBtn');
    btn.disabled = true;
    btn.textContent = 'Enregistrement en cours…';

    try {
        const response = await fetch(API_BASE + 'create_matches.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ week: currentWeek, matches })
        });
        const data = await response.json();
        if (data.success) {
            alert('Rencontres créées avec succès !');
            // recharge l’affichage si besoin…
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        console.error(err);
        alert('Erreur : ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Enregistrer les rencontres';
    }
});