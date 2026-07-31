<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Résultats QCM Examen</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

<div id="denied-screen" class="page hidden" style="padding-top:40px;">
    <div class="brand"><img src="assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
    <div class="panel" style="align-items:center;text-align:center;">
        <p>Vous n'avez pas accès aux résultats.</p>
        <a href="formateur.php" class="btn" style="margin-top:10px;">Retour à l'Espace formateur</a>
    </div>
</div>

<div id="resultats-screen" class="page wide hidden">
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
    <nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><a href="formateur.php">Espace formateur</a><span class="sep">/</span><span class="current">Résultats QCM Examen</span></div></nav>
    <div class="header-spacer"></div>
    <script src="assets/header-fix.js"></script>

    <h2 style="margin-bottom:12px;">À corriger</h2>
    <div class="table-wrap panel" style="padding:0;margin-bottom:24px;">
        <table>
            <thead><tr><th>QCM Examen</th><th>Candidat</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Date</th><th></th></tr></thead>
            <tbody id="attempts-todo-tbody"></tbody>
        </table>
    </div>

    <h2 style="margin-bottom:12px;">Déjà corrigés</h2>
    <div class="table-wrap panel" style="padding:0;">
        <table>
            <thead><tr><th>QCM Examen</th><th>Candidat</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Date</th><th></th></tr></thead>
            <tbody id="attempts-done-tbody"></tbody>
        </table>
    </div>
    <p class="msg" id="attempts-msg"></p>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script>
function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const STATUT_LABELS = {
    en_cours: '<span class="pill">En cours</span>',
    terminee: '<span class="pill ok">Terminée</span>',
    expiree: '<span class="pill warn">Expirée</span>',
};

let canManageAttempts = false;

// Même logique d'affichage que l'onglet Résultats de l'administration
// (admin/app.js, renderAttemptRow) : « à corriger » = résultat pas publié.
function renderAttemptRow(a) {
    let noteCell = '—';
    let resultCell = '—';
    if (a.score !== null) {
        noteCell = `${a.score} / ${a.note_max}`;
        if (!a.resultat_publie) {
            resultCell = '<span class="pill warn">Non publié</span>';
        } else if (!a.afficher_score) {
            resultCell = '<span class="pill">Masqué au candidat</span>';
        } else {
            resultCell = a.reussi ? '<span class="pill ok">Réussi</span>' : '<span class="pill warn">Non validé</span>';
        }
    } else if (a.a_des_questions_ouvertes && a.statut !== 'en_cours') {
        resultCell = '<span class="pill warn">À corriger</span>';
    }
    const action = (canManageAttempts && a.statut !== 'en_cours')
        ? `<a class="secondary btn" style="padding:6px 12px;font-size:0.85rem;" href="admin/index.php?tab=attempts">Corriger</a>`
        : '';
    return `
    <tr>
        <td>${escapeHtml(a.quiz_nom)}</td>
        <td>${escapeHtml(a.candidat)}</td>
        <td>${STATUT_LABELS[a.statut] || a.statut}</td>
        <td>${noteCell}</td>
        <td>${resultCell}</td>
        <td>${escapeHtml(a.started_at)}</td>
        <td class="row-actions">${action}</td>
    </tr>
`;
}

async function loadAttempts() {
    const todoBody = document.getElementById('attempts-todo-tbody');
    const doneBody = document.getElementById('attempts-done-tbody');
    try {
        const res = await fetch('api/quizzes.php?action=attempts');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        if (res.status === 403) {
            document.getElementById('resultats-screen').classList.add('hidden');
            document.getElementById('denied-screen').classList.remove('hidden');
            return;
        }
        const attempts = await res.json();
        if (!Array.isArray(attempts) || !attempts.length) {
            todoBody.innerHTML = `<tr><td colspan="7" style="color:var(--text-secondary);">Rien à corriger pour le moment.</td></tr>`;
            doneBody.innerHTML = `<tr><td colspan="7" style="color:var(--text-secondary);">Aucun résultat pour le moment.</td></tr>`;
            return;
        }
        const todo = attempts.filter(a => a.statut === 'en_cours' || !a.resultat_publie);
        const done = attempts.filter(a => a.statut !== 'en_cours' && a.resultat_publie);
        todoBody.innerHTML = todo.length ? todo.map(renderAttemptRow).join('') : `<tr><td colspan="7" style="color:var(--text-secondary);">Rien à corriger pour le moment.</td></tr>`;
        doneBody.innerHTML = done.length ? done.map(renderAttemptRow).join('') : `<tr><td colspan="7" style="color:var(--text-secondary);">Aucun résultat corrigé pour le moment.</td></tr>`;
    } catch (err) {
        document.getElementById('attempts-msg').textContent = 'Erreur de chargement des résultats';
    }
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
        const perms = data.permissions || {};
        if ((!data.has_formateur_access && data.role !== 'super_admin') || !perms.attempts || perms.attempts === 'none') {
            document.getElementById('denied-screen').classList.remove('hidden');
            return;
        }

        canManageAttempts = data.role === 'super_admin' || perms.attempts === 'manage';
        document.getElementById('welcome-msg').textContent = `Connecté en tant que ${data.username}`;
        document.getElementById('resultats-screen').classList.remove('hidden');
        if (window.qaSyncFixedHeader) window.qaSyncFixedHeader();

        await loadAttempts();
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
