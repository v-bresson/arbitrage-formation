// ===================================================================
// Logique de l'espace admin : auth, CRUD questions, import CSV/XLSX,
// CRUD questionnaires, consultation des résultats.
// ===================================================================

const screens = {
    setup: document.getElementById('setup-screen'),
    login: document.getElementById('login-screen'),
    admin: document.getElementById('admin-screen'),
};

function showScreen(name) {
    Object.values(screens).forEach(s => s.classList.add('hidden'));
    screens[name].classList.remove('hidden');
}

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

let questions = [];
let quizzes = [];

// ---------- Session / connexion ----------
async function checkSession() {
    try {
        const statusRes = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=status',
        });
        const statusData = await statusRes.json();
        if (!statusData.configured) { showScreen('setup'); return; }

        const res = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (data.authenticated) {
            showScreen('admin');
            initAdmin();
        } else {
            showScreen('login');
        }
    } catch (err) {
        showScreen('login');
    }
}

document.getElementById('setup-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('setup-username-input').value;
    const password = document.getElementById('setup-password-input').value;
    const confirmPwd = document.getElementById('setup-password-confirm-input').value;
    const errorEl = document.getElementById('setup-error');
    errorEl.textContent = '';

    if (password !== confirmPwd) { errorEl.textContent = 'Les mots de passe ne correspondent pas'; return; }

    const btn = document.getElementById('setup-btn');
    btn.disabled = true;
    try {
        const res = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=setup&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`,
        });
        const data = await res.json();
        if (data.success) { showScreen('admin'); initAdmin(); }
        else errorEl.textContent = data.message || 'Impossible de créer le compte';
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
    }
});

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username-input').value;
    const password = document.getElementById('password-input').value;
    const errorEl = document.getElementById('login-error');
    errorEl.textContent = '';

    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    try {
        const res = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`,
        });
        const data = await res.json();
        if (data.success) { showScreen('admin'); initAdmin(); }
        else errorEl.textContent = data.message || 'Identifiant ou mot de passe incorrect';
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
    }
});

document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
        await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout',
        });
    } catch (err) { /* on déconnecte quand même côté écran */ }
    showScreen('login');
});

// ---------- Onglets ----------
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
        document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
        if (btn.dataset.tab === 'quizzes') loadQuizzes();
        if (btn.dataset.tab === 'attempts') loadAttempts();
    });
});

function initAdmin() {
    loadQuestions();
}

// ================= QUESTIONS =================
async function loadQuestions() {
    const msg = document.getElementById('questions-msg');
    try {
        const res = await fetch('../api/questions.php?action=list');
        questions = await res.json();
        renderCategoryFilter();
        renderQuestions();
    } catch (err) {
        msg.textContent = 'Erreur de chargement des questions';
    }
}

function renderCategoryFilter() {
    const select = document.getElementById('category-filter');
    const current = select.value;
    const categories = [...new Set(questions.map(q => q.categorie))].sort();
    select.innerHTML = '<option value="">Toutes les catégories</option>' +
        categories.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
    select.value = current;
}

function renderQuestions() {
    const filter = document.getElementById('category-filter').value;
    const tbody = document.getElementById('questions-tbody');
    const filtered = filter ? questions.filter(q => q.categorie === filter) : questions;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="color:var(--text-secondary);">Aucune question. Ajoutez-en une ou importez un fichier.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(q => `
        <tr>
            <td>${escapeHtml(q.categorie)}</td>
            <td>${escapeHtml(q.enonce.slice(0, 80))}${q.enonce.length > 80 ? '…' : ''}</td>
            <td>${q.bonne_reponse.toUpperCase()}</td>
            <td>${q.points}</td>
            <td>${q.actif ? '<span class="pill ok">Active</span>' : '<span class="pill">Inactive</span>'}</td>
            <td class="row-actions">
                <button type="button" class="secondary edit-q-btn" data-id="${q.id}">Modifier</button>
                <button type="button" class="danger delete-q-btn" data-id="${q.id}">Supprimer</button>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('.edit-q-btn').forEach(btn => btn.addEventListener('click', () => openQuestionModal(btn.dataset.id)));
    tbody.querySelectorAll('.delete-q-btn').forEach(btn => btn.addEventListener('click', () => deleteQuestion(btn.dataset.id)));
}

document.getElementById('category-filter').addEventListener('change', renderQuestions);

const questionModal = document.getElementById('question-modal-overlay');
const questionForm = document.getElementById('question-form');

function openQuestionModal(id) {
    const modalMsg = document.getElementById('question-modal-msg');
    modalMsg.textContent = '';
    questionForm.reset();
    document.getElementById('q-id').value = '';
    document.getElementById('q-points').value = 1;
    document.getElementById('q-actif').checked = true;
    document.getElementById('question-modal-title').textContent = 'Nouvelle question';

    if (id) {
        const q = questions.find(x => String(x.id) === String(id));
        if (q) {
            document.getElementById('question-modal-title').textContent = 'Modifier la question';
            document.getElementById('q-id').value = q.id;
            document.getElementById('q-categorie').value = q.categorie;
            document.getElementById('q-points').value = q.points;
            document.getElementById('q-enonce').value = q.enonce;
            document.getElementById('q-a').value = q.options.a || '';
            document.getElementById('q-b').value = q.options.b || '';
            document.getElementById('q-c').value = q.options.c || '';
            document.getElementById('q-d').value = q.options.d || '';
            document.getElementById('q-bonne').value = q.bonne_reponse;
            document.getElementById('q-actif').checked = q.actif;
        }
    }
    questionModal.classList.remove('hidden');
}

document.getElementById('new-question-btn').addEventListener('click', () => openQuestionModal(null));
document.getElementById('question-cancel-btn').addEventListener('click', () => questionModal.classList.add('hidden'));
questionModal.addEventListener('click', e => { if (e.target === questionModal) questionModal.classList.add('hidden'); });

questionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const modalMsg = document.getElementById('question-modal-msg');
    modalMsg.textContent = '';
    const saveBtn = document.getElementById('question-save-btn');
    saveBtn.disabled = true;

    const payload = {
        id: document.getElementById('q-id').value || null,
        categorie: document.getElementById('q-categorie').value,
        enonce: document.getElementById('q-enonce').value,
        options: {
            a: document.getElementById('q-a').value,
            b: document.getElementById('q-b').value,
            c: document.getElementById('q-c').value,
            d: document.getElementById('q-d').value,
        },
        bonne_reponse: document.getElementById('q-bonne').value,
        points: parseInt(document.getElementById('q-points').value, 10) || 1,
        actif: document.getElementById('q-actif').checked,
    };

    try {
        const res = await fetch('../api/questions.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            questionModal.classList.add('hidden');
            await loadQuestions();
        } else {
            modalMsg.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        modalMsg.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

async function deleteQuestion(id) {
    if (!confirm('Supprimer définitivement cette question ?')) return;
    try {
        const res = await fetch('../api/questions.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.success) await loadQuestions();
    } catch (err) {
        document.getElementById('questions-msg').textContent = 'Erreur de connexion au serveur';
    }
}

// ---------- Import CSV / XLSX ----------
const importModal = document.getElementById('import-modal-overlay');
document.getElementById('import-btn').addEventListener('click', () => {
    document.getElementById('import-form').reset();
    document.getElementById('import-msg').textContent = '';
    document.getElementById('import-msg').className = 'msg';
    importModal.classList.remove('hidden');
});
document.getElementById('import-cancel-btn').addEventListener('click', () => importModal.classList.add('hidden'));
importModal.addEventListener('click', e => { if (e.target === importModal) importModal.classList.add('hidden'); });

document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fileInput = document.getElementById('import-file-input');
    const msgEl = document.getElementById('import-msg');
    msgEl.textContent = '';
    msgEl.className = 'msg';
    if (!fileInput.files.length) return;

    const submitBtn = document.getElementById('import-submit-btn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Import en cours...';

    try {
        const formData = new FormData();
        formData.append('fichier', fileInput.files[0]);
        const res = await fetch('../api/questions.php?action=import', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            msgEl.className = 'msg success';
            let text = `${data.inserted} question(s) importée(s).`;
            if (data.errors && data.errors.length) text += ` ${data.errors.length} ligne(s) ignorée(s) : ${data.errors.join(' | ')}`;
            msgEl.textContent = text;
            await loadQuestions();
        } else {
            msgEl.className = 'msg error';
            msgEl.textContent = data.message || "Erreur lors de l'import";
        }
    } catch (err) {
        msgEl.className = 'msg error';
        msgEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Importer';
    }
});

// ================= QUESTIONNAIRES =================
async function loadQuizzes() {
    const grid = document.getElementById('quizzes-grid');
    const msg = document.getElementById('quizzes-msg');
    try {
        const res = await fetch('../api/quizzes.php?action=list');
        quizzes = await res.json();

        if (!quizzes.length) {
            grid.innerHTML = '';
            msg.textContent = "Aucun questionnaire. Créez-en un pour permettre aux candidats de passer l'évaluation.";
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
                    <span class="pill">Seuil : ${q.seuil_reussite}</span>
                    <span class="pill ${q.questions_disponibles < q.nombre_questions ? 'warn' : 'ok'}">${q.questions_disponibles} question(s) disponible(s)</span>
                    <span class="pill ${q.actif ? 'ok' : ''}">${q.actif ? 'Actif' : 'Inactif'}</span>
                </div>
                <div class="row-actions" style="margin-top:8px;">
                    <button type="button" class="secondary edit-qz-btn" data-id="${q.id}">Modifier</button>
                    <button type="button" class="danger delete-qz-btn" data-id="${q.id}">Supprimer</button>
                </div>
            </div>
        `).join('');

        grid.querySelectorAll('.edit-qz-btn').forEach(btn => btn.addEventListener('click', () => openQuizModal(btn.dataset.id)));
        grid.querySelectorAll('.delete-qz-btn').forEach(btn => btn.addEventListener('click', () => deleteQuiz(btn.dataset.id)));
    } catch (err) {
        msg.textContent = 'Erreur de chargement des questionnaires';
    }
}

const quizModal = document.getElementById('quiz-modal-overlay');
const quizForm = document.getElementById('quiz-form');

function openQuizModal(id) {
    const modalMsg = document.getElementById('quiz-modal-msg');
    modalMsg.textContent = '';
    quizForm.reset();
    document.getElementById('qz-id').value = '';
    document.getElementById('qz-nombre').value = 10;
    document.getElementById('qz-notemax').value = 20;
    document.getElementById('qz-seuil').value = 10;
    document.getElementById('qz-actif').checked = true;
    document.getElementById('quiz-modal-title').textContent = 'Nouveau questionnaire';

    if (id) {
        const q = quizzes.find(x => String(x.id) === String(id));
        if (q) {
            document.getElementById('quiz-modal-title').textContent = 'Modifier le questionnaire';
            document.getElementById('qz-id').value = q.id;
            document.getElementById('qz-nom').value = q.nom;
            document.getElementById('qz-desc').value = q.description || '';
            document.getElementById('qz-categorie').value = q.categorie_filtre || '';
            document.getElementById('qz-nombre').value = q.nombre_questions;
            document.getElementById('qz-notemax').value = q.note_max;
            document.getElementById('qz-seuil').value = q.seuil_reussite;
            document.getElementById('qz-actif').checked = q.actif;
        }
    }
    quizModal.classList.remove('hidden');
}

document.getElementById('new-quiz-btn').addEventListener('click', () => openQuizModal(null));
document.getElementById('quiz-cancel-btn').addEventListener('click', () => quizModal.classList.add('hidden'));
quizModal.addEventListener('click', e => { if (e.target === quizModal) quizModal.classList.add('hidden'); });

quizForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const modalMsg = document.getElementById('quiz-modal-msg');
    modalMsg.textContent = '';
    const saveBtn = document.getElementById('quiz-save-btn');
    saveBtn.disabled = true;

    const payload = {
        id: document.getElementById('qz-id').value || null,
        nom: document.getElementById('qz-nom').value,
        description: document.getElementById('qz-desc').value,
        categorie_filtre: document.getElementById('qz-categorie').value,
        nombre_questions: parseInt(document.getElementById('qz-nombre').value, 10) || 1,
        note_max: parseFloat(document.getElementById('qz-notemax').value) || 20,
        seuil_reussite: parseFloat(document.getElementById('qz-seuil').value) || 0,
        actif: document.getElementById('qz-actif').checked,
    };

    try {
        const res = await fetch('../api/quizzes.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            quizModal.classList.add('hidden');
            await loadQuizzes();
        } else {
            modalMsg.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        modalMsg.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

async function deleteQuiz(id) {
    if (!confirm('Supprimer ce questionnaire ? Les résultats associés seront également supprimés.')) return;
    try {
        const res = await fetch('../api/quizzes.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.success) await loadQuizzes();
    } catch (err) {
        document.getElementById('quizzes-msg').textContent = 'Erreur de connexion au serveur';
    }
}

// ================= RESULTATS =================
async function loadAttempts() {
    const tbody = document.getElementById('attempts-tbody');
    try {
        const res = await fetch('../api/quizzes.php?action=attempts');
        const attempts = await res.json();

        if (!attempts.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="color:var(--text-secondary);">Aucune tentative enregistrée pour le moment.</td></tr>`;
            return;
        }

        tbody.innerHTML = attempts.map(a => `
            <tr>
                <td>${escapeHtml(a.quiz_nom)}</td>
                <td>${escapeHtml(a.candidat)}</td>
                <td>${a.score} / ${a.note_max}</td>
                <td>${a.reussi ? '<span class="pill ok">Réussi</span>' : '<span class="pill warn">Non validé</span>'}</td>
                <td>${escapeHtml(a.created_at)}</td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:var(--danger);">Erreur de chargement des résultats</td></tr>`;
    }
}

document.getElementById('year').textContent = new Date().getFullYear();
checkSession();
