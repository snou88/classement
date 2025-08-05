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
        Swal.fire({
            title: '❌ Erreur de chargement',
            text: 'Erreur au chargement des équipes : ' + data.message,
            icon: 'error',
            confirmButtonText: 'Fermer',
            showClass: {
              popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
              popup: 'animate__animated animate__fadeOutUp'
            }
          });
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
        const row = document.createElement('div');
        row.classList.add('match-row');

        const homeSelect = document.createElement('select');
        homeSelect.name = `home_team_${i}`;
        homeSelect.required = true;
        addTeamOptions(homeSelect);

        const awaySelect = document.createElement('select');
        awaySelect.name = `away_team_${i}`;
        awaySelect.required = true;
        addTeamOptions(awaySelect);

        // Crée le label et span
        const label = document.createElement('label');
        label.textContent = `Match ${i} :`;

        const vs = document.createElement('span');
        vs.textContent = ' vs ';

        // Ajoute au DOM
        row.appendChild(label);
        row.appendChild(homeSelect);
        row.appendChild(vs);
        row.appendChild(awaySelect);
        container.appendChild(row);

        // Écouteurs pour mettre à jour les autres selects
        homeSelect.addEventListener('change', updateAllSelects);
        awaySelect.addEventListener('change', updateAllSelects);
    }
}

// Remplir les options
function addTeamOptions(select) {
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = '-- Sélectionner une équipe --';
    select.appendChild(defaultOption);

    teams.forEach(team => {
        const option = document.createElement('option');
        option.value = team.id;
        option.textContent = team.name;
        select.appendChild(option);
    });
}

// Met à jour tous les selects pour éviter les doublons
function updateAllSelects() {
    const allSelects = document.querySelectorAll('#matchesContainer select');
    const selectedValues = Array.from(allSelects)
        .map(select => select.value)
        .filter(val => val !== '');

    allSelects.forEach(select => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => {
            if (option.value === '' || option.value === currentValue) {
                option.hidden = false;
            } else {
                option.hidden = selectedValues.includes(option.value);
            }
        });
    });
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
        container.innerHTML = '<p style="text-align: center; color: #666; margin-bottom: 15px;margin-top: 15px;font-size: large;">Aucune rencontre créée pour cette semaine.</p>';
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
                    <img src="${homeTeam.logo_url}" alt="${homeTeam.name}" class="team-logo">
                    <span>${homeTeam.name}</span>
                </div>
                <span class="vs">VS</span>
                <div class="team">
                    <span>${awayTeam.name}</span>
                    <img src="${awayTeam.logo_url}" alt="${awayTeam.name}" class="team-logo">
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
                    <img src="${homeTeam.logo_url}" alt="${homeTeam.name}" class="team-logo">
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
                    <img src="${awayTeam.logo_url}" alt="${awayTeam.name}" class="team-logo">
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
/**
 * Retourne true si tous les matchs ont un score renseigné, false sinon.
 */
function areAllWeekMatchesFilled() {
    if (weekMatches.length === 0) {
        return true;
    }
    const allFilled = weekMatches.every(match => {
      // on considère "filled" dès que home_goals et away_goals sont à la fois non null, non undefined, et non vides
      const hg = match.home_goals;
      const ag = match.away_goals;
      return hg != null && hg != undefined && hg != '' 
          && ag != null && ag != undefined && ag != '';
    });
    return allFilled;
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
            Swal.fire({
                title: '❌ Erreur',
                text: 'Erreur : ' + data.message,
                icon: 'error',
                confirmButtonText: 'Fermer'
              });
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

// Fonction pour confirmer tous les résultats
async function confirmAllResults() {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: 'Cette action mettra à jour le classement.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, confirmer',
        cancelButtonText: 'Annuler'
      }).then((result) => {
        if (result.isConfirmed) {
          // ✅ L’utilisateur a confirmé → exécuter la suite
          confirmerResultats(); // <-- ta fonction ou ton code ici
        } else {
          // ❌ L’utilisateur a annulé → ne rien faire
          console.log('Confirmation annulée.');
        }
      });
                                                                   
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
            Swal.fire({
                title: '✅ Résultats confirmés',
                text: 'Le classement a été mis à jour.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
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
            Swal.fire({
                title: '❌ Erreur',
                text: 'Erreur : ' + data.message,
                icon: 'error',
                confirmButtonText: 'Fermer'
              });
        }
    } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
            title: '❌ Erreur',
            text: 'Erreur de connexion',
            icon: 'error',
            confirmButtonText: 'Fermer'
          });
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
            Swal.fire({
                title: '⚠️ Équipe en double',
                text: `Le match ${i} a la même équipe à domicile et à l'extérieur !`,
                icon: 'warning',
                confirmButtonText: 'OK',
                showClass: {
                  popup: 'animate__animated animate__shakeX'
                },
                hideClass: {
                  popup: 'animate__animated animate__fadeOut'
                }
              });
            return;
        }
        matches.push({ home_team: homeId, away_team: awayId });
    }

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
            Swal.fire({
                text: 'Rencontres créées avec succès !',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        console.error(err);
        Swal.fire({
            title: '❌ Erreur',
            text: err.message,
            icon: 'error',
            confirmButtonText: 'Fermer'
        });
        btn.disabled = false;
        btn.textContent = 'Enregistrer les rencontres';
    }
});