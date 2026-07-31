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
    <div id="timer-bar" class="timer-bar hidden">
        <span id="timer-label">Temps restant</span>
        <span id="timer-value">--:--</span>
    </div>
    <div class="progress-bar"><div class="fill" id="progress-fill"></div></div>
    <div id="questions-container"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
        <button type="button" id="submit-quiz-btn">Valider mes réponses</button>
    </div>
    <p class="msg error" id="quiz-error"></p>
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
const vm = qaReactive({
    screen: 'list', // 'list' | 'start' | 'quiz' | 'result'
    quizzes: null, // null = pas encore chargé
    listMsg: '',
    currentQuiz: null,
    startError: '',
    startBusy: false,
    result: null, // { withScore, note, note_max, reussi, detail, expiredMsg } | { withScore: false }
});

function bind() {
    document.getElementById('list-screen').classList.toggle('hidden', vm.screen !== 'list');
    document.getElementById('start-screen').classList.toggle('hidden', vm.screen !== 'start');
    document.getElementById('quiz-screen').classList.toggle('hidden', vm.screen !== 'quiz');
    document.getElementById('result-screen').classList.toggle('hidden', vm.screen !== 'result');

    const grid = document.getElementById('quiz-grid');
    document.getElementById('list-msg').textContent = vm.listMsg;
    if (vm.quizzes && vm.quizzes.length) {
        grid.innerHTML = vm.quizzes.map(q => {
            const closed = !!q.ferme;
            const notEnough = !q.suffisant;
            const disabled = closed || notEnough;
            let btnLabel = 'Démarrer';
            if (closed) btnLabel = q.ferme;
            else if (notEnough) btnLabel = 'Pas assez de questions';

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
                    ${q.tentatives_max ? `<span class="pill">${q.tentatives_max} tentative(s) max</span>` : ''}
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
        document.getElementById('welcome-msg').textContent = `Connecté en tant que ${data.username}`;
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
document.getElementById('back-home-btn').addEventListener('click', () => { vm.screen = 'list'; loadQuizList(); });

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
            answers = {};
            renderQuestions();
            setupTimer(data.started_at, data.duree_minutes);
            vm.screen = 'quiz';
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
        input.addEventListener('change', () => {
            const qid = label.dataset.qid;
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
        });
    });

    container.querySelectorAll('.open-answer').forEach(textarea => {
        textarea.addEventListener('input', () => {
            const qid = textarea.dataset.qid;
            if (textarea.value.trim() !== '') answers[qid] = textarea.value;
            else delete answers[qid];
            updateProgress();
        });
    });

    updateProgress();
}

async function submitQuiz(auto) {
    const errorEl = document.getElementById('quiz-error');
    errorEl.textContent = '';

    if (!auto && Object.keys(answers).length < currentQuestions.length) {
        if (!confirm('Certaines questions sont sans réponse. Valider quand même ?')) return;
    }

    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

    const btn = document.getElementById('submit-quiz-btn');
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
            errorEl.textContent = data.message || 'Erreur lors de la validation';
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Valider mes réponses';
    }
}

document.getElementById('submit-quiz-btn').addEventListener('click', () => submitQuiz(false));

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
})();
</script>

</body>
</html>
