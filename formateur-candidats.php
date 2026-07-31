<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Liste des candidats</title>
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

<div id="candidats-screen" class="page wide hidden">
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
    <nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><a href="formateur.php">Espace formateur</a><span class="sep">/</span><span class="current">Liste des candidats</span></div></nav>
    <div class="header-spacer"></div>
    <script src="assets/header-fix.js"></script>

    <h2 style="margin-bottom:12px;">Candidats</h2>
    <div class="field" style="max-width:420px;margin:0 auto 20px;">
        <label>Filtrer (identifiant, nom, prénom, club)</label>
        <input type="text" id="candidats-filter-input" placeholder="Filtrer les candidats...">
    </div>
    <div class="table-wrap panel" style="padding:0;">
        <table>
            <thead><tr><th>Nom</th><th>Club</th><th>Niveau</th><th>Option</th><th>Formateurs référents</th><th>Actif</th><th></th></tr></thead>
            <tbody id="candidats-tbody"></tbody>
        </table>
    </div>
    <p class="msg" id="candidats-msg"></p>

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

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script>
function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formateurLabel(f) {
    return [f.prenom, f.nom].filter(Boolean).join(' ') || f.username;
}

let allCandidats = [];

function renderCandidatsTable(list) {
    const tbody = document.getElementById('candidats-tbody');
    const msg = document.getElementById('candidats-msg');
    if (!list.length) {
        tbody.innerHTML = '';
        msg.textContent = 'Aucun candidat trouvé.';
        return;
    }
    msg.textContent = '';
    tbody.innerHTML = list.map(c => `
        <tr>
            <td>${escapeHtml([c.prenom, c.nom].filter(Boolean).join(' ')) || '—'}</td>
            <td>${escapeHtml(c.club || '—')}</td>
            <td>${escapeHtml(c.niveau_formation || '—')}</td>
            <td>${escapeHtml(c.option_pratique || '—')}</td>
            <td>${escapeHtml((c.formateurs || []).map(formateurLabel).join(', ') || '—')}</td>
            <td>${c.actif ? '<span class="pill ok">Actif</span>' : '<span class="pill">Inactif</span>'}</td>
            <td class="row-actions"><button type="button" class="secondary voir-fiche-btn" data-id="${c.id}">Voir la fiche</button></td>
        </tr>
    `).join('');
    tbody.querySelectorAll('.voir-fiche-btn').forEach(btn => btn.addEventListener('click', () => showFiche(btn.dataset.id)));
}

function applyFilter() {
    const q = document.getElementById('candidats-filter-input').value.trim().toLowerCase();
    if (!q) { renderCandidatsTable(allCandidats); return; }
    renderCandidatsTable(allCandidats.filter(c => [c.username, c.nom, c.prenom, c.club].filter(Boolean).some(v => v.toLowerCase().includes(q))));
}

async function loadCandidats() {
    try {
        const res = await fetch('api/mes_candidats.php?action=list');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        const list = await res.json();
        allCandidats = Array.isArray(list) ? list : [];
        applyFilter();
    } catch (err) {
        document.getElementById('candidats-msg').textContent = 'Erreur de chargement des candidats';
    }
}

document.getElementById('candidats-filter-input').addEventListener('input', applyFilter);

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

        document.getElementById('welcome-msg').textContent = `Connecté en tant que ${data.username}`;
        document.getElementById('candidats-screen').classList.remove('hidden');
        if (window.qaSyncFixedHeader) window.qaSyncFixedHeader();

        await loadCandidats();
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
