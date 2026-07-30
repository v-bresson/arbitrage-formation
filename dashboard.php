<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

<header class="site-header">
    <div class="site-header-row">
        <div class="brand"><img src="assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
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
    <div class="grid" id="spaces-grid"></div>
    <p class="msg" id="spaces-msg" style="text-align:center;margin-top:20px;"></p>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script src="assets/mvvm.js"></script>
<script>
const ICONS = {
    target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
};

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const vm = qaReactive({
    username: null,
    hasFormateurAccess: false,
    hasPureAdminAccess: false,
});

function bind() {
    document.getElementById('welcome-msg').textContent = vm.username ? `Connecté en tant que ${vm.username}` : '';

    const spaces = [
        { nom: 'Espace candidat', description: 'Vos stages, questionnaires et parcours de formation.', href: 'candidate.php', icone: 'target' },
    ];
    if (vm.hasFormateurAccess) {
        spaces.push({ nom: 'Espace formateur', description: 'Suivi des candidats : questions, questionnaires et résultats.', href: 'formateur.php', icone: 'users' });
    }
    if (vm.hasPureAdminAccess) {
        spaces.push({ nom: 'Administration', description: 'Comptes utilisateurs et maintenance système.', href: 'admin/index.php', icone: 'lock' });
    }

    document.getElementById('spaces-grid').innerHTML = spaces.map(s => `
        <a class="card" href="${escapeHtml(s.href)}" style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[s.icone]}</svg>
            </div>
            <h2>${escapeHtml(s.nom)}</h2>
            <p>${escapeHtml(s.description)}</p>
        </a>
    `).join('');
}
qaWatchEffect(bind);

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
        vm.hasFormateurAccess = !!data.has_formateur_access;
        vm.hasPureAdminAccess = !!data.has_pure_admin_access;
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
