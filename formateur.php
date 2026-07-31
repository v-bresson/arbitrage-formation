<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Espace formateur</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

<div id="denied-screen" class="page hidden" style="padding-top:40px;">
    <div class="brand"><img src="assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
    <div class="panel" style="align-items:center;text-align:center;">
        <p>Cet espace est réservé aux formateurs et membres CRA.</p>
        <a href="dashboard.php" class="btn" style="margin-top:10px;">Retour au dashboard</a>
    </div>
</div>

<div id="formateur-screen" class="page wide hidden">
    <header class="site-header">
        <div class="site-header-row">
            <div class="brand"><img src="assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
            <h1 style="flex:1;text-align:center;font-size:1.3rem;color:var(--text-primary);">Gestionnaire de formations</h1>
            <div style="display:flex;gap:14px;align-items:center;">
                <span id="welcome-msg" style="color:var(--text-secondary);font-size:0.9rem;"></span>
                <button type="button" class="secondary" id="logout-btn">Se déconnecter</button>
            </div>
        </div>
    </header>
    <nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><span class="current">Espace formateur</span></div></nav>
    <div class="header-spacer"></div>
    <script src="assets/header-fix.js"></script>

    <div class="grid" id="stats-grid" style="margin-bottom:24px;"></div>

    <div class="grid" id="links-grid" style="margin-bottom:24px;"></div>

    <!-- ---------- CANDIDATS : recherche + fiche ---------- -->
    <div id="candidats-section">
        <h2 style="margin-bottom:12px;">Candidats</h2>
        <div class="field" style="max-width:420px;margin:0 auto 20px;">
            <label>Rechercher un candidat (identifiant, nom, prénom, club)</label>
            <input type="text" id="candidats-search-input" placeholder="Rechercher un candidat...">
        </div>
        <div class="table-wrap panel hidden" id="candidats-search-results" style="padding:0;">
            <table>
                <thead><tr><th>Identifiant</th><th>Nom</th><th>Club</th><th></th></tr></thead>
                <tbody id="candidats-search-tbody"></tbody>
            </table>
        </div>
        <p class="msg" id="candidats-search-msg"></p>

        <div id="candidat-fiche" class="hidden" style="margin-top:24px;">
            <div class="panel" id="fiche-profile-card" style="margin-bottom:24px;flex-direction:row;flex-wrap:wrap;gap:28px;">
                <div>
                    <p class="modal-hint" style="margin:0;">Nom</p>
                    <p id="fiche-nom" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">Prénom</p>
                    <p id="fiche-prenom" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">Club</p>
                    <p id="fiche-club" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">N° de licence</p>
                    <p id="fiche-licence" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">Niveau de formation</p>
                    <p id="fiche-niveau" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">Option</p>
                    <p id="fiche-option" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
                <div>
                    <p class="modal-hint" style="margin:0;">Date d'entrée en formation</p>
                    <p id="fiche-date-entree" style="font-weight:600;font-size:1.05rem;">—</p>
                </div>
            </div>
            <div class="grid" id="fiche-stats-grid" style="margin-bottom:24px;"></div>
        </div>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script src="assets/mvvm.js"></script>
<script>
const ICONS = {
    clipboard: '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
    trophy: '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3a2 2 0 0 1 0 4h-1"/><path d="M7 5H4a2 2 0 0 0 0 4h1"/>',
    target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
};

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const vm = qaReactive({
    username: null,
    permissions: {},
    stats: null,
});

function canRead(section) {
    return vm.permissions[section] && vm.permissions[section] !== 'none';
}

function bind() {
    document.getElementById('welcome-msg').textContent = vm.username ? `Connecté en tant que ${vm.username}` : '';

    const s = vm.stats;
    document.getElementById('stats-grid').innerHTML = !s ? '' : `
        <div class="card"><p style="color:var(--text-secondary);">Candidats actifs</p><h2 style="font-size:2rem;">${s.candidats}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Questionnaires passés</p><h2 style="font-size:2rem;">${s.total_tentatives}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Réussis</p><h2 style="font-size:2rem;">${s.reussies}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Dernière tentative</p><h2 style="font-size:1.3rem;">${s.derniere_tentative ? new Date(s.derniere_tentative).toLocaleDateString('fr-FR') : '—'}</h2></div>
    `;

    const links = [];
    if (canRead('questions')) links.push({ nom: 'Banque de questions', description: 'Consulter ou gérer les questions.', tab: 'questions', icone: 'clipboard' });
    if (canRead('quizzes')) links.push({ nom: 'QCM Examen', description: 'Consulter ou gérer les QCM Examen.', tab: 'quizzes', icone: 'target' });
    if (canRead('attempts')) links.push({ nom: 'Résultats', description: 'Suivre les tentatives des candidats.', tab: 'attempts', icone: 'trophy' });

    document.getElementById('links-grid').innerHTML = links.map(l => `
        <a class="card" href="admin/index.php?tab=${escapeHtml(l.tab)}" style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[l.icone]}</svg>
            </div>
            <h2>${escapeHtml(l.nom)}</h2>
            <p>${escapeHtml(l.description)}</p>
        </a>
    `).join('');
}
qaWatchEffect(bind);

async function loadStats() {
    try {
        const res = await fetch('api/attempt.php?action=formateur-stats');
        if (!res.ok) return;
        vm.stats = await res.json();
    } catch (err) { /* pas bloquant */ }
}

// ---------- Candidats : recherche (bornée par rôle côté API, voir
// api/mes_candidats.php) + fiche en lecture seule ----------
let candidatsSearchTimer = null;

function renderCandidatsSearchResults(list) {
    const tbody = document.getElementById('candidats-search-tbody');
    const msg = document.getElementById('candidats-search-msg');
    if (!list.length) {
        tbody.innerHTML = '';
        msg.textContent = 'Aucun candidat trouvé.';
        return;
    }
    msg.textContent = '';
    tbody.innerHTML = list.map(c => `
        <tr>
            <td>${escapeHtml(c.username)}</td>
            <td>${escapeHtml([c.prenom, c.nom].filter(Boolean).join(' ')) || '—'}</td>
            <td>${escapeHtml(c.club || '—')}</td>
            <td class="row-actions"><button type="button" class="secondary voir-fiche-btn" data-id="${c.id}">Voir la fiche</button></td>
        </tr>
    `).join('');
    tbody.querySelectorAll('.voir-fiche-btn').forEach(btn => btn.addEventListener('click', () => showFiche(btn.dataset.id)));
}

async function searchCandidats(q) {
    try {
        const res = await fetch('api/mes_candidats.php?action=search&q=' + encodeURIComponent(q));
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        const list = await res.json();
        renderCandidatsSearchResults(Array.isArray(list) ? list : []);
    } catch (err) {
        document.getElementById('candidats-search-msg').textContent = 'Erreur de chargement des candidats';
    }
}

document.getElementById('candidats-search-input').addEventListener('input', (e) => {
    clearTimeout(candidatsSearchTimer);
    const q = e.target.value.trim();
    const resultsBox = document.getElementById('candidats-search-results');
    if (!q) {
        resultsBox.classList.add('hidden');
        document.getElementById('candidats-search-tbody').innerHTML = '';
        document.getElementById('candidats-search-msg').textContent = '';
        return;
    }
    resultsBox.classList.remove('hidden');
    candidatsSearchTimer = setTimeout(() => searchCandidats(q), 250);
});

async function showFiche(id) {
    try {
        const res = await fetch('api/mes_candidats.php?action=fiche&id=' + encodeURIComponent(id));
        if (!res.ok) return;
        const f = await res.json();
        document.getElementById('fiche-prenom').textContent = f.prenom || '—';
        document.getElementById('fiche-nom').textContent = f.nom || '—';
        document.getElementById('fiche-club').textContent = f.club || '—';
        document.getElementById('fiche-licence').textContent = f.numero_licence || '—';
        document.getElementById('fiche-niveau').textContent = f.niveau_formation || '—';
        document.getElementById('fiche-option').textContent = f.option_pratique || '—';
        document.getElementById('fiche-date-entree').textContent = f.date_entree_formation
            ? new Date(f.date_entree_formation).toLocaleDateString('fr-FR') : '—';

        const s = f.stats || {};
        document.getElementById('fiche-stats-grid').innerHTML = `
            <div class="card"><p style="color:var(--text-secondary);">Questionnaires complétés</p><h2 style="font-size:2rem;">${s.total_tentatives ?? 0}</h2></div>
            <div class="card"><p style="color:var(--text-secondary);">Réussis</p><h2 style="font-size:2rem;">${s.reussies ?? 0}</h2></div>
            <div class="card"><p style="color:var(--text-secondary);">Score moyen</p><h2 style="font-size:2rem;">${s.moyenne_pct !== null && s.moyenne_pct !== undefined ? s.moyenne_pct + '%' : '—'}</h2></div>
            <div class="card"><p style="color:var(--text-secondary);">Dernière tentative</p><h2 style="font-size:1.3rem;">${s.derniere_tentative ? new Date(s.derniere_tentative).toLocaleDateString('fr-FR') : '—'}</h2></div>
        `;
        document.getElementById('candidat-fiche').classList.remove('hidden');
        document.getElementById('candidat-fiche').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) { /* pas bloquant */ }
}

async function init() {
    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) { window.location.href = 'index.php'; return; }
        if (!data.has_formateur_access && data.role !== 'super_admin') {
            document.getElementById('denied-screen').classList.remove('hidden');
            return;
        }

        vm.username = data.username;
        vm.permissions = data.permissions || {};
        document.getElementById('formateur-screen').classList.remove('hidden');
        if (window.qaSyncFixedHeader) window.qaSyncFixedHeader();

        await loadStats();
    } catch (err) {
        window.location.href = 'index.php';
    }
}

document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
        await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout',
        });
    } catch (err) { /* on déconnecte quand même côté écran */ }
    window.location.href = 'index.php';
});

document.getElementById('year').textContent = new Date().getFullYear();
init();
</script>

</body>
</html>
