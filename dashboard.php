<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps Judging — Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

<header class="site-header">
    <div class="site-header-row">
        <div class="brand"><img src="assets/logo.png" alt="ArcheryOps Judging"></div>
        <div style="display:flex;gap:14px;align-items:center;">
            <span id="welcome-msg" style="color:var(--text-secondary);font-size:0.9rem;"></span>
            <button type="button" class="secondary" id="logout-btn">Se déconnecter</button>
        </div>
    </div>
</header>
<nav class="breadcrumb"><div class="breadcrumb-row"><span class="current">Dashboard</span></div></nav>
<div class="header-spacer"></div>
<script src="assets/header-fix.js"></script>

<div class="page wide">
    <div class="grid" id="tiles-grid"></div>
    <p class="msg" id="tiles-msg" style="text-align:center;margin-top:20px;"></p>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps Judging</footer>

<script src="assets/mvvm.js"></script>
<script>
const ICONS = {
    target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    trophy: '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3a2 2 0 0 1 0 4h-1"/><path d="M7 5H4a2 2 0 0 0 0 4h1"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    clipboard: '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
    wifi: '<path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
};

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ---------- ViewModel ----------
// Utilisateur connecté, rôle, tuiles chargées et message d'état : la
// vue (bind()) se redessine seule dès qu'une de ces propriétés change.
const vm = qaReactive({
    username: null,
    role: null,
    hasAdminAccess: false,
    tiles: null, // null = pas encore chargé
    tilesMsg: '',
});

function bind() {
    document.getElementById('welcome-msg').textContent = vm.username ? `Connecté en tant que ${vm.username}` : '';

    const grid = document.getElementById('tiles-grid');
    const msg = document.getElementById('tiles-msg');
    msg.textContent = vm.tilesMsg;

    const tiles = vm.tiles ? [...vm.tiles] : [];
    if (vm.hasAdminAccess) {
        tiles.push({ nom: 'Administration', description: 'Questions, questionnaires, comptes utilisateurs et maintenance.', type: 'admin', icone: 'lock' });
    }

    if (!tiles.length) {
        grid.innerHTML = '';
        return;
    }

    grid.innerHTML = tiles.map(t => {
        const href = t.type === 'questionnaire' ? 'quiz.php' : (t.type === 'admin' ? 'admin/index.php' : t.url);
        const target = t.type === 'lien' ? ' target="_blank" rel="noopener noreferrer"' : '';
        return `
        <a class="card" href="${escapeHtml(href)}"${target} style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[t.icone] || ICONS.info}</svg>
            </div>
            <h2>${escapeHtml(t.nom)}</h2>
            <p>${escapeHtml(t.description || '')}</p>
        </a>
    `;
    }).join('');
}
qaWatchEffect(bind);

// ---------- Méthodes du ViewModel ----------
async function init() {
    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) { window.location.href = 'index.php'; return; }

        vm.username = data.username;
        vm.role = data.role;
        vm.hasAdminAccess = !!data.has_admin_access;

        await loadTiles();
    } catch (err) {
        window.location.href = 'index.php';
    }
}

async function loadTiles() {
    try {
        const res = await fetch('api/tiles.php?action=list');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        const tiles = await res.json();
        vm.tiles = tiles;
        vm.tilesMsg = (tiles.length || vm.hasAdminAccess) ? '' : 'Aucune tuile configurée pour le moment.';
    } catch (err) {
        vm.tilesMsg = 'Erreur de chargement des tuiles';
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
