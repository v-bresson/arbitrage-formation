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

    <h2 id="formateur-title" style="margin-bottom:16px;"></h2>
    <div class="grid" id="stats-grid" style="margin-bottom:24px;"></div>

    <div class="grid" id="tiles-grid"></div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script src="assets/mvvm.js"></script>
<script>
const ICONS = {
    trophy: '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3a2 2 0 0 1 0 4h-1"/><path d="M7 5H4a2 2 0 0 0 0 4h1"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
};

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const vm = qaReactive({
    username: null,
    permissions: {},
    dashboardStats: null,
    nom: null,
    prenom: null,
});

function canRead(section) {
    return vm.permissions[section] && vm.permissions[section] !== 'none';
}

// Prénom Nom si renseignés, sinon repli sur l'identifiant technique.
function fullName() {
    return [vm.prenom, vm.nom].filter(Boolean).join(' ') || vm.username;
}

function bind() {
    document.getElementById('welcome-msg').textContent = vm.username ? `Connecté en tant que ${fullName()}` : '';
    document.getElementById('formateur-title').textContent = vm.username ? `Espace Formateur de ${fullName()}` : '';

    const s = vm.dashboardStats;
    document.getElementById('stats-grid').innerHTML = !s ? '' : `
        <div class="card"><p style="color:var(--text-secondary);">Nombre total de candidats</p><h2 style="font-size:2rem;">${s.candidats_total}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Candidats Assistant Arbitre</p><h2 style="font-size:2rem;">${s.candidats_assistant}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Candidats Arbitre Fédéral</p><h2 style="font-size:2rem;">${s.candidats_federal}</h2></div>
        <div class="card"><p style="color:var(--text-secondary);">Candidats Arbitre Duel</p><h2 style="font-size:2rem;">${s.candidats_duel}</h2></div>
    `;

    const tiles = [];
    if (canRead('attempts')) {
        const nb = s ? s.a_corriger : 0;
        tiles.push({
            nom: 'Résultats QCM Examen',
            description: 'Suivre et corriger les tentatives des candidats.',
            alert: nb
                ? `<div class="tile-alert">${nb} correction${nb > 1 ? 's' : ''} en attente</div>`
                : `<div class="tile-alert ok">Aucune correction en attente</div>`,
            href: 'formateur-resultats.php',
            icone: 'trophy',
        });
    }
    tiles.push({ nom: 'Liste des candidats', description: 'Consulter la fiche de vos candidats.', href: 'formateur-candidats.php', icone: 'users' });
    tiles.push({ nom: 'Gestion des stages', description: 'Ce module arrive bientôt.', href: 'formateur-stages.php', icone: 'calendar' });
    tiles.push({ nom: 'Sessions de formations', description: 'Ce module arrive bientôt.', href: 'formateur-sessions.php', icone: 'calendar' });

    document.getElementById('tiles-grid').innerHTML = tiles.map(t => `
        <a class="card" href="${escapeHtml(t.href)}" style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[t.icone]}</svg>
            </div>
            <h2>${escapeHtml(t.nom)}</h2>
            <p>${escapeHtml(t.description)}</p>
            ${t.alert || ''}
        </a>
    `).join('');
}
qaWatchEffect(bind);

async function loadDashboardStats() {
    try {
        const res = await fetch('api/mes_candidats.php?action=dashboard_stats');
        if (!res.ok) return;
        vm.dashboardStats = await res.json();
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
        vm.nom = data.nom;
        vm.prenom = data.prenom;
        vm.permissions = data.permissions || {};
        document.getElementById('formateur-screen').classList.remove('hidden');
        if (window.qaSyncFixedHeader) window.qaSyncFixedHeader();

        await loadDashboardStats();
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
