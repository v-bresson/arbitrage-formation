<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — QCM Examen</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

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
<nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><a href="candidate.php">Espace candidat</a><span class="sep">/</span><span class="current">QCM Examen</span></div></nav>
<div class="header-spacer"></div>
<script src="assets/header-fix.js"></script>

<!-- ================= LISTE DES QUESTIONNAIRES ================= -->
<div id="list-screen" class="page wide hidden">
    <div class="grid" id="quiz-grid"></div>
    <p class="msg" id="list-msg" style="text-align:center;margin-top:20px;"></p>

    <!-- ---------- MES TENTATIVES : historique + relecture ---------- -->
    <div id="attempts-history-section" style="margin-top:32px;">
        <h2 style="margin-bottom:16px;text-align:left;">Mes tentatives</h2>
        <div class="table-wrap panel" style="padding:0;">
            <table>
                <thead><tr><th>QCM Examen</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Début</th><th></th></tr></thead>
                <tbody id="attempts-history-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= ECRAN RELECTURE D'UNE TENTATIVE PASSEE ================= -->
<div id="history-review-screen" class="page wide hidden">
    <h2 id="history-review-title" style="margin-bottom:6px;"></h2>
    <p id="history-review-meta" style="color:var(--text-secondary);margin-bottom:16px;"></p>
    <div id="history-review-score" class="panel hidden" style="text-align:center;align-items:center;margin-bottom:20px;"></div>
    <div id="history-review-container"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:10px;">
        <button type="button" class="secondary" id="history-review-back-btn">Retour</button>
    </div>
</div>

<!-- ================= ECRAN DE DEMARRAGE ================= -->
<div id="start-screen" class="page hidden">
    <div class="panel">
        <h2 id="start-quiz-name"></h2>
        <p id="start-quiz-desc" style="color:var(--text-secondary);font-size:0.9rem;"></p>
        <div class="meta" id="start-quiz-meta" style="margin-bottom:6px;"></div>
        <p style="color:var(--text-secondary);font-size:0.9rem;">Vous allez commencer en tant que <strong id="start-candidat-name"></strong>.</p>
        <div style="display:flex;gap:10px;margin-top:10px;">
            <button type="button" class="secondary" id="back-to-list-btn">Retour</button>
            <button type="button" id="start-btn" style="flex:1;">Commencer le QCM Examen</button>
        </div>
        <div class="msg error" id="start-error"></div>
    </div>
</div>

<!-- ================= ECRAN QUESTIONNAIRE ================= -->
<div id="quiz-screen" class="page wide hidden">
    <div id="quiz-progress-sticky" class="quiz-progress-sticky">
        <div id="timer-bar" class="timer-bar hidden">
            <span id="timer-label">Temps restant</span>
            <span id="timer-value">--:--</span>
        </div>
        <div class="progress-bar"><div class="fill" id="progress-fill"></div></div>
    </div>
    <div id="questions-container"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
        <button type="button" id="submit-quiz-btn">Valider mes réponses</button>
    </div>
    <p class="msg error" id="quiz-error"></p>
</div>

<!-- ================= ECRAN RELECTURE AVANT ENVOI ================= -->
<div id="review-screen" class="page wide hidden">
    <h2 style="margin-bottom:6px;">Relecture avant envoi</h2>
    <p style="color:var(--text-secondary);margin-bottom:16px;">Vérifiez vos réponses avant l'envoi définitif. Vous pourrez encore les modifier après.</p>
    <div id="review-container"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
        <button type="button" class="secondary" id="review-back-btn">Modifier mes réponses</button>
        <button type="button" id="review-confirm-btn">Confirmer l'envoi</button>
    </div>
</div>

<!-- ================= ECRAN RESULTAT ================= -->
<div id="result-screen" class="page hidden">
    <div class="panel" style="text-align:center;align-items:center;">
        <div id="result-with-score">
            <p style="color:var(--text-secondary);">Votre note</p>
            <div class="result-score" id="result-score">-</div>
            <p id="result-detail" style="color:var(--text-secondary);"></p>
            <div class="pill" id="result-pill" style="font-size:0.95rem;padding:8px 18px;"></div>
        </div>
        <div id="result-no-score" class="hidden">
            <p style="font-size:1.1rem;margin-bottom:8px;">Vos réponses ont bien été enregistrées.</p>
            <p style="color:var(--text-secondary);">Le résultat de ce QCM Examen ne s'affiche pas immédiatement et vous sera communiqué séparément.</p>
        </div>
        <p class="msg error" id="result-expired-msg"></p>
        <button type="button" id="back-home-btn" style="margin-top:16px;">Retour à la liste</button>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script src="assets/mvvm.js"></script>
<script>
function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ---------- ViewModel ----------
// Écran affiché, liste des questionnaires, infos du questionnaire choisi
// et résultat final : la vue (bind()) se redessine seule dès qu'une de
// ces propriétés change. Le questionnaire en cours de passage (liste des
// questions, réponses saisies, minuteur) reste volontairement en dehors
// de cet état réactif — voir la note dans assets/mvvm.js : un ré-rendu
// complet à chaque réponse cochée ou chaque tic du minuteur casserait le
// focus des champs et la sélection en cours.
const RESULT_PILLS = {
    reussi: '<span class="pill ok">Réussi</span>',
    non_valide: '<span class="pill warn">Non validé</span>',
    en_attente: '<span class="pill">En attente de correction</span>',
};

const STATUT_LABELS = {
    en_cours: '<span class="pill">En cours</span>',
    terminee: '<span class="pill ok">Terminée</span>',
    expiree: '<span class="pill warn">Expirée</span>',
};

const vm = qaReactive({
    screen: 'list', // 'list' | 'start' | 'quiz' | 'review' | 'result' | 'history-review'
    quizzes: null, // null = pas encore chargé
    listMsg: '',
    currentQuiz: null,
    startError: '',
    startBusy: false,
    result: null, // { withScore, note, note_max, reussi, detail, expiredMsg } | { withScore: false }
    attemptsHistory: null, // null = pas encore chargé
});

function bind() {
    document.getElementById('list-screen').classList.toggle('hidden', vm.screen !== 'list');
    document.getElementById('start-screen').classList.toggle('hidden', vm.screen !== 'start');
    document.getElementById('quiz-screen').classList.toggle('hidden', vm.screen !== 'quiz');
    document.getElementById('review-screen').classList.toggle('hidden', vm.screen !== 'review');
    document.getElementById('result-screen').classList.toggle('hidden', vm.screen !== 'result');
    document.getElementById('history-review-screen').classList.toggle('hidden', vm.screen !== 'history-review');

    const grid = document.getElementById('quiz-grid');
    document.getElementById('list-msg').textContent = vm.listMsg;
    if (vm.quizzes && vm.quizzes.length) {
        grid.innerHTML = vm.quizzes.map(q => {
            const closed = !!q.ferme;
            const notEnough = !q.suffisant;
            const maxReached = q.tentatives_max && q.mes_tentatives >= q.tentatives_max && !q.tentative_en_cours;
            const disabled = closed || notEnough || maxReached;
            let btnLabel = q.tentative_en_cours ? 'Reprendre' : 'Démarrer';
            if (closed) btnLabel = q.ferme;
            else if (notEnough) btnLabel = 'Pas assez de questions';
            else if (maxReached) btnLabel = 'Nombre de tentatives atteint';

            return `
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <h2>${escapeHtml(q.nom)}</h2>
                    <span class="pill ${q.type === 'examen' ? 'warn' : 'ok'}">${q.type === 'examen' ? 'Examen' : 'Entraînement'}</span>
                </div>
                <p>${escapeHtml(q.description || '')}</p>
                <div class="meta">
                    <span class="pill">${q.nombre_questions} question(s)</span>
                    <span class="pill">Note sur ${q.note_max}</span>
                    <span class="pill">Seuil de réussite : ${q.seuil_reussite}</span>
                    ${q.duree_minutes ? `<span class="pill">${q.duree_minutes} min chrono</span>` : ''}
                    <span class="pill">${q.mes_tentatives} tentative(s) réalisée(s)${q.tentatives_max ? ' / ' + q.tentatives_max : ''}</span>
                    ${q.tentative_en_cours ? '<span class="pill warn">Tentative en cours</span>' : ''}
                    ${q.dernier_resultat ? RESULT_PILLS[q.dernier_resultat] || '' : ''}
                </div>
                <button type="button" class="start-quiz-btn" data-id="${q.id}" data-nom="${escapeHtml(q.nom)}" data-desc="${escapeHtml(q.description || '')}" data-type="${q.type}" data-duree="${q.duree_minutes || ''}" data-tentatives="${q.tentatives_max || ''}" ${disabled ? 'disabled' : ''}>
                    ${btnLabel}
                </button>
            </div>
        `;
        }).join('');
        grid.querySelectorAll('.start-quiz-btn').forEach(btn => btn.addEventListener('click', () => openStartScreen(btn.dataset)));
    } else {
        grid.innerHTML = '';
    }

    const historyTbody = document.getElementById('attempts-history-tbody');
    const history = vm.attemptsHistory || [];
    document.getElementById('attempts-history-section').classList.toggle('hidden', vm.attemptsHistory !== null && history.length === 0);
    if (!history.length) {
        historyTbody.innerHTML = `<tr><td colspan="6" style="color:var(--text-secondary);">Aucune tentative pour le moment.</td></tr>`;
    } else {
        historyTbody.innerHTML = history.map(a => {
            let noteCell = '—';
            let resultCell = '—';
            if (a.statut !== 'en_cours' && a.resultat_publie) {
                if (a.afficher_score) {
                    noteCell = `${a.score} / ${a.note_max}`;
                    resultCell = a.reussi ? RESULT_PILLS.reussi : RESULT_PILLS.non_valide;
                } else {
                    resultCell = '<span class="pill">Corrigé</span>';
                }
            } else if (a.statut !== 'en_cours') {
                resultCell = RESULT_PILLS.en_attente;
            }
            const canReview = a.statut !== 'en_cours';
            return `
            <tr>
                <td>${escapeHtml(a.quiz_nom)}</td>
                <td>${STATUT_LABELS[a.statut] || a.statut}</td>
                <td>${noteCell}</td>
                <td>${resultCell}</td>
                <td>${escapeHtml(a.started_at)}</td>
                <td class="row-actions">${canReview ? `<button type="button" class="secondary history-review-btn" data-id="${a.id}">Relecture</button>` : ''}</td>
            </tr>`;
        }).join('');
        historyTbody.querySelectorAll('.history-review-btn').forEach(btn => btn.addEventListener('click', () => openHistoryReview(btn.dataset.id)));
    }

    if (vm.currentQuiz) {
        document.getElementById('start-quiz-name').textContent = vm.currentQuiz.nom;
        document.getElementById('start-quiz-desc').textContent = vm.currentQuiz.desc;
        document.getElementById('start-candidat-name').textContent = vm.currentQuiz.username;
        const metaEl = document.getElementById('start-quiz-meta');
        let metaHtml = `<span class="pill ${vm.currentQuiz.type === 'examen' ? 'warn' : 'ok'}">${vm.currentQuiz.type === 'examen' ? 'Examen' : 'Entraînement'}</span>`;
        if (vm.currentQuiz.duree) metaHtml += `<span class="pill">${vm.currentQuiz.duree} min chrono</span>`;
        if (vm.currentQuiz.tentatives) metaHtml += `<span class="pill">${vm.currentQuiz.tentatives} tentative(s) max</span>`;
        metaEl.innerHTML = metaHtml;
    }
    document.getElementById('start-error').textContent = vm.startError;
    const startBtn = document.getElementById('start-btn');
    startBtn.disabled = vm.startBusy;
    startBtn.textContent = vm.startBusy ? 'Chargement...' : 'Commencer le QCM Examen';

    if (vm.result) {
        document.getElementById('result-expired-msg').textContent = vm.result.expiredMsg || '';
        document.getElementById('result-with-score').classList.toggle('hidden', !vm.result.withScore);
        document.getElementById('result-no-score').classList.toggle('hidden', vm.result.withScore);
        if (vm.result.withScore) {
            const scoreEl = document.getElementById('result-score');
            scoreEl.textContent = `${vm.result.note} / ${vm.result.note_max}`;
            scoreEl.className = 'result-score ' + (vm.result.reussi ? 'ok' : 'ko');
            document.getElementById('result-detail').textContent = vm.result.detail;
            const pill = document.getElementById('result-pill');
            pill.textContent = vm.result.reussi ? 'Réussi' : 'Non validé';
            pill.className = 'pill ' + (vm.result.reussi ? 'ok' : 'warn');
        }
    }
}
qaWatchEffect(bind);

// ---------- Passage du questionnaire (imperatif, hors ViewModel réactif) ----------
let currentUsername = null;
let currentQuestions = [];
let answers = {};
let tentativeId = null;
let timerInterval = null;
let deadlineTs = null;

// Cale le minuteur/l'avancement, sticky, juste sous le bandeau fixe + le
// fil d'ariane : header-fix.js mesure déjà leur hauteur réelle dans
// .header-spacer, on la réutilise pour ne pas dupliquer le calcul.
function syncStickyProgress() {
    const spacer = document.querySelector('.header-spacer');
    const sticky = document.getElementById('quiz-progress-sticky');
    if (!spacer || !sticky) return;
    sticky.style.top = spacer.style.height || '0px';
}
window.addEventListener('resize', syncStickyProgress);

async function checkAuth() {
    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) {
            window.location.href = 'index.php';
            return false;
        }
        currentUsername = data.username;
        document.getElementById('welcome-msg').textContent = `Connecté en tant que ${[data.prenom, data.nom].filter(Boolean).join(' ') || data.username}`;
        return true;
    } catch (err) {
        window.location.href = 'index.php';
        return false;
    }
}

async function loadQuizList() {
    try {
        const res = await fetch('api/attempt.php?action=quizzes');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        const quizzes = await res.json();
        vm.quizzes = quizzes;
        vm.listMsg = quizzes.length ? '' : "Aucun QCM Examen n'est disponible pour le moment.";
    } catch (err) {
        vm.listMsg = 'Erreur de connexion au serveur';
    }
}

async function loadAttemptsHistory() {
    try {
        const res = await fetch('api/attempt.php?action=my-attempts');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        vm.attemptsHistory = await res.json();
    } catch (err) {
        vm.attemptsHistory = [];
    }
}

// ---------- Relecture d'une tentative passée (voir #history-review-screen) ----------
// Même principe d'affichage que la relecture avant envoi (renderReview) :
// toutes les options visibles, réponse saisie en surbrillance ; si le
// résultat a été publié, la bonne réponse est en plus signalée en vert et
// une réponse fausse en rouge, avec le score au-dessus.
async function openHistoryReview(id) {
    try {
        const res = await fetch('api/attempt.php?action=review&id=' + encodeURIComponent(id));
        if (!res.ok) return;
        const t = await res.json();

        document.getElementById('history-review-title').textContent = t.quiz_nom;
        document.getElementById('history-review-meta').textContent =
            `Débuté le ${t.started_at}${t.completed_at ? ', terminé le ' + t.completed_at : ''}`;

        const scoreEl = document.getElementById('history-review-score');
        if (t.resultat_publie && t.afficher_score) {
            scoreEl.classList.remove('hidden');
            scoreEl.innerHTML = `
                <p style="color:var(--text-secondary);">Votre note</p>
                <div class="result-score ${t.reussi ? 'ok' : 'ko'}">${t.score} / ${t.note_max}</div>
                <div class="pill ${t.reussi ? 'ok' : 'warn'}" style="font-size:0.95rem;padding:8px 18px;">${t.reussi ? 'Réussi' : 'Non validé'}</div>
            `;
        } else if (t.resultat_publie) {
            scoreEl.classList.remove('hidden');
            scoreEl.innerHTML = `<p style="font-size:1rem;">Ce QCM Examen a été corrigé, mais le résultat n'est pas affiché pour ce questionnaire.</p>`;
        } else {
            scoreEl.classList.remove('hidden');
            scoreEl.innerHTML = `<p style="font-size:1rem;color:var(--text-secondary);">Le résultat de cette tentative n'a pas encore été publié.</p>`;
        }

        const container = document.getElementById('history-review-container');
        container.innerHTML = t.questions.map((q, i) => {
            const image = q.image ? `<img src="${escapeHtml(q.image)}" alt="" class="question-image">` : '';
            let body;

            if (q.type === 'ouverte') {
                body = q.reponse_donnee
                    ? `<div class="option-label selected" style="cursor:default;"><span style="white-space:pre-wrap;">${escapeHtml(q.reponse_donnee)}</span></div>`
                    : '<p style="color:var(--text-secondary);">Sans réponse</p>';
            } else {
                const given = q.type === 'qcm_multiple'
                    ? (Array.isArray(q.reponse_donnee) ? q.reponse_donnee : [])
                    : (q.reponse_donnee ? [q.reponse_donnee] : []);
                const correct = (q.bonne_reponse || '').split(',').filter(Boolean);
                body = Object.entries(q.options).map(([key, text]) => {
                    const isGiven = given.includes(key);
                    const isCorrect = t.resultat_publie && correct.includes(key);
                    const classes = ['option-label'];
                    if (isGiven) classes.push('selected');
                    if (isCorrect) classes.push('correct');
                    if (isGiven && t.resultat_publie && !isCorrect) classes.push('incorrect');
                    return `<div class="${classes.join(' ')}" style="cursor:default;"><span>${escapeHtml(text)}</span></div>`;
                }).join('');
            }

            const pointsInfo = t.resultat_publie && q.points_max !== undefined
                ? `<p style="color:var(--text-secondary);font-size:0.85rem;">${q.points_attribues} / ${q.points_max} point(s)</p>` : '';

            return `
            <div class="question-block">
                <h3>${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span>${q.type === 'qcm_multiple' ? '<span class="pill warn">Réponses multiples</span>' : ''}</h3>
                ${image}
                ${body}
                ${pointsInfo}
            </div>`;
        }).join('');

        vm.screen = 'history-review';
        window.scrollTo({ top: 0, behavior: 'auto' });
    } catch (err) { /* pas bloquant */ }
}

document.getElementById('history-review-back-btn').addEventListener('click', () => { vm.screen = 'list'; });

function openStartScreen(dataset) {
    vm.currentQuiz = {
        id: dataset.id, nom: dataset.nom, desc: dataset.desc,
        type: dataset.type, duree: dataset.duree, tentatives: dataset.tentatives,
        username: currentUsername,
    };
    vm.startError = '';
    vm.screen = 'start';
}

document.getElementById('back-to-list-btn').addEventListener('click', () => { vm.screen = 'list'; });
document.getElementById('back-home-btn').addEventListener('click', () => { vm.screen = 'list'; loadQuizList(); loadAttemptsHistory(); });

document.getElementById('start-btn').addEventListener('click', async () => {
    vm.startError = '';
    vm.startBusy = true;

    try {
        const res = await fetch('api/attempt.php?action=start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `quiz_id=${encodeURIComponent(vm.currentQuiz.id)}`,
        });
        const data = await res.json();
        if (data.success) {
            currentQuestions = data.questions;
            tentativeId = data.tentative_id;
            // En cas de reprise d'une tentative en cours, les réponses déjà
            // saisies avant une fermeture de fenêtre sont restaurées (voir
            // api/attempt.php, action=save_progress).
            answers = data.reponses && typeof data.reponses === 'object' ? { ...data.reponses } : {};
            renderQuestions();
            setupTimer(data.started_at, data.duree_minutes);
            syncStickyProgress();
            vm.screen = 'quiz';
            if (progressIntervalId) clearInterval(progressIntervalId);
            progressIntervalId = setInterval(() => saveProgressNow(false), 20000);
        } else {
            vm.startError = data.message || 'Impossible de démarrer le QCM Examen';
        }
    } catch (err) {
        vm.startError = 'Erreur de connexion au serveur';
    } finally {
        vm.startBusy = false;
    }
});

// ---------- Minuteur ----------
function setupTimer(startedAt, dureeMinutes) {
    const bar = document.getElementById('timer-bar');
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

    if (!dureeMinutes) {
        bar.classList.add('hidden');
        deadlineTs = null;
        return;
    }

    // startedAt est une date serveur "YYYY-MM-DD HH:MM:SS" ; on la traite comme
    // heure locale (le serveur et les candidats sont sur le même fuseau).
    const startTs = new Date(startedAt.replace(' ', 'T')).getTime();
    deadlineTs = startTs + dureeMinutes * 60 * 1000;
    bar.classList.remove('hidden');

    const tick = () => {
        const remainingMs = deadlineTs - Date.now();
        if (remainingMs <= 0) {
            document.getElementById('timer-value').textContent = '00:00';
            clearInterval(timerInterval);
            timerInterval = null;
            submitQuiz(true);
            return;
        }
        const totalSec = Math.floor(remainingMs / 1000);
        const min = String(Math.floor(totalSec / 60)).padStart(2, '0');
        const sec = String(totalSec % 60).padStart(2, '0');
        document.getElementById('timer-value').textContent = `${min}:${sec}`;
        document.getElementById('timer-bar').classList.toggle('urgent', totalSec <= 60);
    };
    tick();
    timerInterval = setInterval(tick, 1000);
}

// ---------- Sauvegarde périodique des réponses en cours ----------
// Sans ça, fermer la fenêtre pendant l'examen fait perdre les réponses
// déjà saisies (seul le minuteur, recalculé depuis started_at, survit).
let saveProgressTimer = null;
let progressIntervalId = null;

function saveProgressNow(useBeacon) {
    if (!tentativeId) return;
    const payload = JSON.stringify({ tentative_id: tentativeId, reponses: answers });
    if (useBeacon && navigator.sendBeacon) {
        navigator.sendBeacon('api/attempt.php?action=save_progress', new Blob([payload], { type: 'application/json' }));
        return;
    }
    fetch('api/attempt.php?action=save_progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
    }).catch(() => { /* pas bloquant : retentera au prochain changement */ });
}

function scheduleSaveProgress() {
    clearTimeout(saveProgressTimer);
    saveProgressTimer = setTimeout(() => saveProgressNow(false), 1200);
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && vm.screen === 'quiz') saveProgressNow(true);
});
window.addEventListener('pagehide', () => {
    if (vm.screen === 'quiz') saveProgressNow(true);
});

function updateProgress() {
    const answered = Object.keys(answers).length;
    const pct = currentQuestions.length ? (answered / currentQuestions.length) * 100 : 0;
    document.getElementById('progress-fill').style.width = pct + '%';
}

function renderQuestions() {
    const container = document.getElementById('questions-container');
    container.innerHTML = currentQuestions.map((q, i) => {
        const image = q.image ? `<img src="${escapeHtml(q.image)}" alt="" class="question-image">` : '';
        let body = '';

        if (q.type === 'qcm_multiple') {
            body = Object.entries(q.options).map(([key, text]) => `
                <label class="option-label" data-qid="${q.id}" data-key="${key}">
                    <input type="checkbox" name="q-${q.id}" value="${key}">
                    <span>${escapeHtml(text)}</span>
                </label>
            `).join('');
        } else if (q.type === 'ouverte') {
            body = `<textarea class="open-answer" data-qid="${q.id}" rows="4" placeholder="Votre réponse..."></textarea>`;
        } else {
            body = Object.entries(q.options).map(([key, text]) => `
                <label class="option-label" data-qid="${q.id}" data-key="${key}">
                    <input type="radio" name="q-${q.id}" value="${key}">
                    <span>${escapeHtml(text)}</span>
                </label>
            `).join('');
        }

        return `
        <div class="question-block">
            <h3>${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span>${q.type === 'qcm_multiple' ? '<span class="pill warn">Réponses multiples</span>' : ''}</h3>
            ${image}
            ${body}
        </div>
    `;
    }).join('');

    container.querySelectorAll('.option-label[data-key]').forEach(label => {
        const input = label.querySelector('input');
        const qid = label.dataset.qid;
        const given = answers[qid];
        // Reprise d'une tentative en cours : pré-coche les options déjà
        // enregistrées (voir action=start / save_progress).
        if (input.type === 'radio' ? given === label.dataset.key : Array.isArray(given) && given.includes(label.dataset.key)) {
            input.checked = true;
            label.classList.add('selected');
        }
        input.addEventListener('change', () => {
            if (input.type === 'radio') {
                answers[qid] = input.value;
                container.querySelectorAll(`.option-label[data-qid="${qid}"]`).forEach(l => l.classList.remove('selected'));
                label.classList.add('selected');
            } else {
                const checked = Array.from(container.querySelectorAll(`.option-label[data-qid="${qid}"] input:checked`)).map(i => i.value);
                answers[qid] = checked;
                label.classList.toggle('selected', input.checked);
            }
            updateProgress();
            scheduleSaveProgress();
        });
    });

    container.querySelectorAll('.open-answer').forEach(textarea => {
        const qid = textarea.dataset.qid;
        if (typeof answers[qid] === 'string') textarea.value = answers[qid];
        textarea.addEventListener('input', () => {
            if (textarea.value.trim() !== '') answers[qid] = textarea.value;
            else delete answers[qid];
            updateProgress();
            scheduleSaveProgress();
        });
    });

    updateProgress();
}

// Aperçu en lecture seule des réponses avant l'envoi définitif (voir
// écran #review-screen) : montre toutes les options de chaque question
// (comme lors de la saisie) avec la ou les réponses saisies mises en
// évidence, pour que la relecture ne masque rien.
function renderReview() {
    const container = document.getElementById('review-container');
    container.innerHTML = currentQuestions.map((q, i) => {
        const image = q.image ? `<img src="${escapeHtml(q.image)}" alt="" class="question-image">` : '';
        let body;
        let unanswered = false;

        if (q.type === 'ouverte') {
            const val = answers[q.id];
            unanswered = !val;
            body = val
                ? `<div class="option-label selected" style="cursor:default;"><span style="white-space:pre-wrap;">${escapeHtml(val)}</span></div>`
                : '<p style="color:var(--text-secondary);">Sans réponse</p>';
        } else {
            const given = q.type === 'qcm_multiple'
                ? (Array.isArray(answers[q.id]) ? answers[q.id] : [])
                : (answers[q.id] ? [answers[q.id]] : []);
            unanswered = given.length === 0;
            body = Object.entries(q.options).map(([key, text]) => `
                <div class="option-label${given.includes(key) ? ' selected' : ''}" style="cursor:default;">
                    <span>${escapeHtml(text)}</span>
                </div>
            `).join('');
        }

        return `
        <div class="question-block">
            <h3>${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span>${q.type === 'qcm_multiple' ? '<span class="pill warn">Réponses multiples</span>' : ''}${unanswered ? '<span class="pill warn">Sans réponse</span>' : ''}</h3>
            ${image}
            ${body}
        </div>`;
    }).join('');
}

document.getElementById('submit-quiz-btn').addEventListener('click', () => {
    renderReview();
    vm.screen = 'review';
    window.scrollTo({ top: 0, behavior: 'auto' });
});
document.getElementById('review-back-btn').addEventListener('click', () => { vm.screen = 'quiz'; });
document.getElementById('review-confirm-btn').addEventListener('click', () => submitQuiz(false, true));

async function submitQuiz(auto, fromReview) {
    const errorEl = document.getElementById('quiz-error');
    errorEl.textContent = '';

    if (!auto && !fromReview && Object.keys(answers).length < currentQuestions.length) {
        if (!confirm('Certaines questions sont sans réponse. Valider quand même ?')) return;
    }

    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    if (progressIntervalId) { clearInterval(progressIntervalId); progressIntervalId = null; }
    clearTimeout(saveProgressTimer);

    const btn = fromReview ? document.getElementById('review-confirm-btn') : document.getElementById('submit-quiz-btn');
    btn.disabled = true;
    btn.textContent = 'Validation...';

    try {
        const res = await fetch('api/attempt.php?action=submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tentative_id: tentativeId, reponses: answers }),
        });
        const data = await res.json();
        if (data.success) {
            const expiredMsg = data.expiree ? "Le temps imparti était écoulé : votre tentative a été enregistrée telle quelle." : '';
            if (data.afficher_score) {
                let detailTxt = `${data.bonnes_reponses} bonne(s) réponse(s) sur ${data.total_questions_notees} question(s) notée(s)`;
                if (data.total_questions > data.total_questions_notees) detailTxt += ` (+ ${data.total_questions - data.total_questions_notees} question(s) ouverte(s) à relire manuellement)`;
                vm.result = { withScore: true, note: data.note, note_max: data.note_max, reussi: data.reussi, detail: detailTxt, expiredMsg };
            } else {
                vm.result = { withScore: false, expiredMsg };
            }
            vm.screen = 'result';
        } else {
            vm.screen = 'quiz';
            errorEl.textContent = data.message || 'Erreur lors de la validation';
        }
    } catch (err) {
        vm.screen = 'quiz';
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
        btn.textContent = fromReview ? "Confirmer l'envoi" : 'Valider mes réponses';
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

(async () => {
    const ok = await checkAuth();
    if (!ok) return;
    vm.screen = 'list';
    loadQuizList();
    loadAttemptsHistory();
})();
</script>

</body>
</html>
