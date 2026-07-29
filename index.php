<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QuizzArbitre — Questionnaires</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="brand">
    <h1>Quizz<span>Arbitre</span></h1>
    <p class="subtitle">Validation de la formation d'arbitre assistant</p>
</div>

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
        <form id="start-form" class="field">
            <label for="candidat-input">Votre nom et prénom</label>
            <input type="text" id="candidat-input" placeholder="Nom Prénom" required autofocus>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="button" class="secondary" id="back-to-list-btn">Retour</button>
                <button type="submit" id="start-btn" style="flex:1;">Commencer le questionnaire</button>
            </div>
            <div class="msg error" id="start-error"></div>
        </form>
    </div>
</div>

<!-- ================= ECRAN QUESTIONNAIRE ================= -->
<div id="quiz-screen" class="page wide hidden">
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
        <p style="color:var(--text-secondary);">Votre note</p>
        <div class="result-score" id="result-score">-</div>
        <p id="result-detail" style="color:var(--text-secondary);"></p>
        <div class="pill" id="result-pill" style="font-size:0.95rem;padding:8px 18px;"></div>
        <button type="button" id="back-home-btn" style="margin-top:16px;">Retour à l'accueil</button>
    </div>
</div>

<footer>&copy; <span id="year"></span> QuizzArbitre</footer>

<script>
const screens = {
    list: document.getElementById('list-screen'),
    start: document.getElementById('start-screen'),
    quiz: document.getElementById('quiz-screen'),
    result: document.getElementById('result-screen'),
};

function showScreen(name) {
    Object.values(screens).forEach(s => s.classList.add('hidden'));
    screens[name].classList.remove('hidden');
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

let currentQuiz = null;
let currentQuestions = [];
let answers = {};

async function loadQuizList() {
    const grid = document.getElementById('quiz-grid');
    const msg = document.getElementById('list-msg');
    try {
        const res = await fetch('api/attempt.php?action=quizzes');
        const quizzes = await res.json();

        if (!quizzes.length) {
            msg.textContent = "Aucun questionnaire n'est disponible pour le moment.";
            grid.innerHTML = '';
            return;
        }

        msg.textContent = '';
        grid.innerHTML = quizzes.map(q => `
            <div class="card">
                <h2>${escapeHtml(q.nom)}</h2>
                <p>${escapeHtml(q.description || '')}</p>
                <div class="meta">
                    <span class="pill">${q.nombre_questions} question(s)</span>
                    <span class="pill">Note sur ${q.note_max}</span>
                    <span class="pill">Seuil de réussite : ${q.seuil_reussite}</span>
                </div>
                <button type="button" class="start-quiz-btn" data-id="${q.id}" data-nom="${escapeHtml(q.nom)}" data-desc="${escapeHtml(q.description || '')}" ${q.questions_disponibles < q.nombre_questions ? 'disabled' : ''}>
                    ${q.questions_disponibles < q.nombre_questions ? 'Pas assez de questions' : 'Démarrer'}
                </button>
            </div>
        `).join('');

        grid.querySelectorAll('.start-quiz-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentQuiz = { id: btn.dataset.id, nom: btn.dataset.nom, desc: btn.dataset.desc };
                document.getElementById('start-quiz-name').textContent = currentQuiz.nom;
                document.getElementById('start-quiz-desc').textContent = currentQuiz.desc;
                document.getElementById('start-error').textContent = '';
                showScreen('start');
            });
        });
    } catch (err) {
        msg.textContent = 'Erreur de connexion au serveur';
    }
}

document.getElementById('back-to-list-btn').addEventListener('click', () => showScreen('list'));
document.getElementById('back-home-btn').addEventListener('click', () => { showScreen('list'); loadQuizList(); });

document.getElementById('start-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const startBtn = document.getElementById('start-btn');
    const candidat = document.getElementById('candidat-input').value;
    const errorEl = document.getElementById('start-error');
    errorEl.textContent = '';
    startBtn.disabled = true;
    startBtn.textContent = 'Chargement...';

    try {
        const res = await fetch('api/attempt.php?action=start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `quiz_id=${encodeURIComponent(currentQuiz.id)}&candidat=${encodeURIComponent(candidat)}`,
        });
        const data = await res.json();
        if (data.success) {
            currentQuestions = data.questions;
            answers = {};
            renderQuestions();
            showScreen('quiz');
        } else {
            errorEl.textContent = data.message || 'Impossible de démarrer le questionnaire';
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        startBtn.disabled = false;
        startBtn.textContent = 'Commencer le questionnaire';
    }
});

function updateProgress() {
    const answered = Object.keys(answers).length;
    const pct = currentQuestions.length ? (answered / currentQuestions.length) * 100 : 0;
    document.getElementById('progress-fill').style.width = pct + '%';
}

function renderQuestions() {
    const container = document.getElementById('questions-container');
    container.innerHTML = currentQuestions.map((q, i) => `
        <div class="question-block">
            <h3>${i + 1}. ${escapeHtml(q.enonce)} <span class="pill">${escapeHtml(q.categorie)}</span></h3>
            ${Object.entries(q.options).map(([key, text]) => `
                <label class="option-label" data-qid="${q.id}" data-key="${key}">
                    <input type="radio" name="q-${q.id}" value="${key}">
                    <span>${escapeHtml(text)}</span>
                </label>
            `).join('')}
        </div>
    `).join('');

    container.querySelectorAll('.option-label').forEach(label => {
        label.addEventListener('click', () => {
            const qid = label.dataset.qid;
            const key = label.dataset.key;
            answers[qid] = key;

            container.querySelectorAll(`.option-label[data-qid="${qid}"]`).forEach(l => l.classList.remove('selected'));
            label.classList.add('selected');
            label.querySelector('input').checked = true;
            updateProgress();
        });
    });

    updateProgress();
}

document.getElementById('submit-quiz-btn').addEventListener('click', async () => {
    const errorEl = document.getElementById('quiz-error');
    errorEl.textContent = '';

    if (Object.keys(answers).length < currentQuestions.length) {
        if (!confirm('Certaines questions sont sans réponse. Valider quand même ?')) return;
    }

    const btn = document.getElementById('submit-quiz-btn');
    btn.disabled = true;
    btn.textContent = 'Validation...';

    try {
        const res = await fetch('api/attempt.php?action=submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reponses: answers }),
        });
        const data = await res.json();
        if (data.success) {
            const scoreEl = document.getElementById('result-score');
            scoreEl.textContent = `${data.note} / ${data.note_max}`;
            scoreEl.className = 'result-score ' + (data.reussi ? 'ok' : 'ko');
            document.getElementById('result-detail').textContent = `${data.bonnes_reponses} bonne(s) réponse(s) sur ${data.total_questions} question(s)`;
            const pill = document.getElementById('result-pill');
            pill.textContent = data.reussi ? 'Réussi' : 'Non validé';
            pill.className = 'pill ' + (data.reussi ? 'ok' : 'warn');
            showScreen('result');
        } else {
            errorEl.textContent = data.message || 'Erreur lors de la validation';
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Valider mes réponses';
    }
});

document.getElementById('year').textContent = new Date().getFullYear();
showScreen('list');
loadQuizList();
</script>

</body>
</html>
