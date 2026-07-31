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

    <h2 style="margin-bottom:12px;">Résultats</h2>
    <!-- Un seul tableau (« À corriger » puis « Déjà corrigés » séparés par une
         ligne de section) pour que les deux groupes partagent exactement les
         mêmes largeurs de colonnes — deux <table> distincts s'ajustaient
         chacun indépendamment et désalignaient les colonnes. -->
    <div class="table-wrap panel" style="padding:0;">
        <table>
            <thead><tr><th>QCM Examen</th><th>Nom</th><th>Prénom</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Date</th><th></th></tr></thead>
            <tbody id="attempts-tbody"></tbody>
        </table>
    </div>
    <p class="msg" id="attempts-msg"></p>
</div>

<!-- ================= MODALE CORRECTION D'UNE TENTATIVE ================= -->
<div id="grade-modal-overlay" class="modal-overlay hidden">
    <div class="modal" style="max-width:760px;">
        <h2 id="grade-modal-title">Relecture / correction</h2>
        <p class="modal-hint" id="grade-modal-meta" style="margin:0;"></p>
        <div id="grade-questions-list" style="display:flex;flex-direction:column;gap:14px;margin-top:10px;"></div>
        <div class="panel" style="margin-top:6px;flex-direction:row;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <p class="modal-hint" style="margin:0;">Note recalculée</p>
                <p id="grade-total" style="font-weight:600;font-size:1.2rem;">—</p>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin:0;"><input type="checkbox" id="grade-publier" style="width:auto;"> Publier la note au candidat</label>
        </div>
        <div class="modal-actions">
            <button type="button" class="secondary" id="grade-cancel-btn">Fermer</button>
            <button type="button" id="grade-save-btn">Enregistrer la correction</button>
        </div>
        <div class="msg error" id="grade-modal-msg"></div>
    </div>
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
// Tentatives (api/quizzes.php) ne renvoient que l'identifiant du candidat ;
// on les recoupe avec la liste des candidats visibles (api/mes_candidats.php)
// pour afficher nom/prénom plutôt que l'identifiant technique.
let candidatsByUsername = {};

// Même logique d'affichage que l'onglet Résultats de l'administration
// (admin/app.js, renderAttemptRow) : « à corriger » = résultat pas publié.
// La correction se fait ici même (modale ci-dessous), un Formateur n'a pas
// accès au dashboard Administration.
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
        ? `<button type="button" class="secondary grade-attempt-btn" data-id="${a.id}" style="padding:6px 12px;font-size:0.85rem;">${a.resultat_publie ? 'Revoir' : 'Corriger'}</button>`
        : '';
    const c = candidatsByUsername[a.candidat];
    return `
    <tr>
        <td>${escapeHtml(a.quiz_nom)}</td>
        <td>${escapeHtml(c ? c.nom : '') || '—'}</td>
        <td>${escapeHtml(c ? c.prenom : '') || '—'}</td>
        <td>${STATUT_LABELS[a.statut] || a.statut}</td>
        <td>${noteCell}</td>
        <td>${resultCell}</td>
        <td>${escapeHtml(a.started_at)}</td>
        <td class="row-actions">${action}</td>
    </tr>
`;
}

function sectionRow(label) {
    return `<tr><td colspan="8" style="background:var(--bg-card-hover);font-weight:600;color:var(--text-secondary);">${escapeHtml(label)}</td></tr>`;
}

async function loadCandidatsByUsername() {
    try {
        const res = await fetch('api/mes_candidats.php?action=list');
        if (!res.ok) return;
        const list = await res.json();
        if (Array.isArray(list)) {
            candidatsByUsername = Object.fromEntries(list.map(c => [c.username, c]));
        }
    } catch (err) { /* pas bloquant, on affichera un tiret */ }
}

async function loadAttempts() {
    const tbody = document.getElementById('attempts-tbody');
    try {
        await loadCandidatsByUsername();
        const res = await fetch('api/quizzes.php?action=attempts');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        if (res.status === 403) {
            document.getElementById('resultats-screen').classList.add('hidden');
            document.getElementById('denied-screen').classList.remove('hidden');
            return;
        }
        const attempts = await res.json();
        if (!Array.isArray(attempts) || !attempts.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="color:var(--text-secondary);">Aucun résultat pour le moment.</td></tr>`;
            return;
        }
        const todo = attempts.filter(a => a.statut === 'en_cours' || !a.resultat_publie);
        const done = attempts.filter(a => a.statut !== 'en_cours' && a.resultat_publie);

        tbody.innerHTML =
            sectionRow('À corriger') +
            (todo.length ? todo.map(renderAttemptRow).join('') : `<tr><td colspan="8" style="color:var(--text-secondary);">Rien à corriger pour le moment.</td></tr>`) +
            sectionRow('Déjà corrigés') +
            (done.length ? done.map(renderAttemptRow).join('') : `<tr><td colspan="8" style="color:var(--text-secondary);">Aucun résultat corrigé pour le moment.</td></tr>`);

        tbody.querySelectorAll('.grade-attempt-btn').forEach(btn => btn.addEventListener('click', () => openGradeModal(btn.dataset.id)));
    } catch (err) {
        document.getElementById('attempts-msg').textContent = 'Erreur de chargement des résultats';
    }
}

// ---------- Modale de relecture/correction (reprise de admin/app.js,
// mêmes endpoints api/quizzes.php déjà bornés aux candidats du formateur) ----------
const gradeModal = document.getElementById('grade-modal-overlay');
let gradeQuestions = [];
let gradeMeta = null;

function renderOptionsWithHighlight(q) {
    const options = q.options || {};
    const given = q.type === 'qcm_multiple'
        ? (Array.isArray(q.reponse_donnee) ? q.reponse_donnee : [])
        : (q.reponse_donnee ? [q.reponse_donnee] : []);
    const correct = (q.bonne_reponse || '').split(',').filter(Boolean);
    return Object.entries(options).filter(([, text]) => text).map(([key, text]) => {
        const isGiven = given.includes(key);
        const isCorrect = correct.includes(key);
        const classes = ['option-label'];
        if (isGiven) classes.push('selected');
        if (isCorrect) classes.push('correct');
        if (isGiven && !isCorrect) classes.push('incorrect');
        return `<div class="${classes.join(' ')}" style="cursor:default;"><span>${escapeHtml(text)}</span></div>`;
    }).join('');
}

function updateGradeTotal() {
    let earned = 0;
    let total = 0;
    document.querySelectorAll('.grade-points-input').forEach(input => {
        const max = parseFloat(input.dataset.max) || 0;
        let val = parseFloat(input.value);
        if (isNaN(val)) val = 0;
        if (val > max) val = max;
        if (val < 0) val = 0;
        earned += val;
        total += max;
    });
    const note = total > 0 ? Math.round((earned / total) * gradeMeta.note_max * 100) / 100 : 0;
    const reussi = note >= gradeMeta.seuil_reussite;
    const totalEl = document.getElementById('grade-total');
    totalEl.textContent = `${note} / ${gradeMeta.note_max} — ${reussi ? 'Réussi' : 'Non validé'}`;
    totalEl.style.color = reussi ? 'var(--success)' : 'var(--danger)';
}

async function openGradeModal(id) {
    const msgEl = document.getElementById('grade-modal-msg');
    msgEl.textContent = '';
    try {
        const res = await fetch('api/quizzes.php?action=attempt_detail&id=' + encodeURIComponent(id));
        if (!res.ok) return;
        const t = await res.json();
        gradeMeta = t;
        gradeQuestions = t.questions;

        document.getElementById('grade-modal-title').textContent = `Relecture — ${t.quiz_nom}`;
        document.getElementById('grade-modal-meta').textContent = `Candidat : ${t.candidat} — Débuté le ${t.started_at}${t.completed_at ? ', terminé le ' + t.completed_at : ''}`;
        document.getElementById('grade-publier').checked = t.resultat_publie;

        const list = document.getElementById('grade-questions-list');
        list.innerHTML = t.questions.map((q, i) => {
            if (q.type === 'ouverte') {
                return `
                <div class="panel" style="gap:8px;">
                    <p style="font-weight:600;">${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span></p>
                    <div class="field"><label>Réponse du candidat</label><textarea rows="3" disabled>${escapeHtml(q.reponse_donnee || '')}</textarea></div>
                    <div class="field" style="max-width:160px;"><label>Points (sur ${q.points_max})</label><input type="number" class="grade-points-input" data-max="${q.points_max}" min="0" max="${q.points_max}" step="0.5" value="${q.points_attribues}"></div>
                </div>`;
            }
            return `
            <div class="panel" style="gap:8px;">
                <p style="font-weight:600;">${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span>${q.type === 'qcm_multiple' ? '<span class="pill warn">Réponses multiples</span>' : ''}</p>
                ${renderOptionsWithHighlight(q)}
                <p style="color:var(--text-secondary);font-size:0.85rem;">Réponse saisie en <span style="color:var(--accent);">bleu</span>, bonne réponse en <span style="color:var(--success);">vert</span>.</p>
                <div class="field" style="max-width:160px;"><label>Points (sur ${q.points_max})</label><input type="number" class="grade-points-input" data-max="${q.points_max}" min="0" max="${q.points_max}" step="0.5" value="${q.points_attribues}"></div>
            </div>`;
        }).join('');

        list.querySelectorAll('.grade-points-input').forEach(input => input.addEventListener('input', updateGradeTotal));
        updateGradeTotal();
        gradeModal.classList.remove('hidden');
    } catch (err) {
        document.getElementById('attempts-msg').textContent = 'Erreur de chargement de la tentative';
    }
}

document.getElementById('grade-cancel-btn').addEventListener('click', () => gradeModal.classList.add('hidden'));
gradeModal.addEventListener('click', e => { if (e.target === gradeModal) gradeModal.classList.add('hidden'); });

document.getElementById('grade-save-btn').addEventListener('click', async () => {
    const msgEl = document.getElementById('grade-modal-msg');
    msgEl.textContent = '';
    const saveBtn = document.getElementById('grade-save-btn');
    saveBtn.disabled = true;

    const inputs = document.querySelectorAll('.grade-points-input');
    const payload = {
        id: gradeMeta.id,
        corrections: gradeQuestions.map((q, i) => ({ question_id: q.id, points: parseFloat(inputs[i].value) || 0 })),
        publier: document.getElementById('grade-publier').checked,
    };

    try {
        const res = await fetch('api/quizzes.php?action=grade_attempt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            gradeModal.classList.add('hidden');
            await loadAttempts();
        } else {
            msgEl.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        msgEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

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
