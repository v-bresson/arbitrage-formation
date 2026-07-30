// ===================================================================
// Logique de l'espace admin : auth, CRUD questions (QCM unique/multiple/
// ouverte + image, réservation examen), import CSV/XLSX, CRUD
// questionnaires (entraînement/examen, chrono, fenêtre d'ouverture,
// tentatives max, affichage du score), consultation des résultats.
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

const QUESTION_TYPE_LABELS = {
    qcm_unique: 'QCM unique',
    qcm_multiple: 'QCM multiple',
    ouverte: 'Ouverte',
};

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

    const datalist = document.getElementById('qz-categories-datalist');
    if (datalist) datalist.innerHTML = categories.map(c => `<option value="${escapeHtml(c)}">`).join('');
}

function formatBonneReponse(q) {
    if (q.type === 'ouverte') return '—';
    if (q.type === 'qcm_multiple') return (q.bonne_reponse || '').split(',').map(l => l.toUpperCase()).join(' + ');
    return (q.bonne_reponse || '').toUpperCase();
}

function renderQuestions() {
    const filter = document.getElementById('category-filter').value;
    const tbody = document.getElementById('questions-tbody');
    const filtered = filter ? questions.filter(q => q.categorie === filter) : questions;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="color:var(--text-secondary);">Aucune question. Ajoutez-en une ou importez un fichier.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(q => `
        <tr>
            <td>${escapeHtml(q.categorie)}</td>
            <td>${QUESTION_TYPE_LABELS[q.type] || q.type}${q.image ? ' 🖼' : ''}</td>
            <td>${escapeHtml(q.enonce.slice(0, 70))}${q.enonce.length > 70 ? '…' : ''}</td>
            <td>${formatBonneReponse(q)}</td>
            <td>${q.points}</td>
            <td>${q.examen_uniquement ? '<span class="pill warn">Examen</span>' : '—'}</td>
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
const qTypeSelect = document.getElementById('q-type');

function updateQuestionTypeFields() {
    const type = qTypeSelect.value;
    document.getElementById('q-options-fields').classList.toggle('hidden', type === 'ouverte');
    document.getElementById('q-ouverte-hint').style.display = type === 'ouverte' ? 'block' : 'none';
    document.getElementById('q-bonne-unique-field').classList.toggle('hidden', type !== 'qcm_unique');
    document.getElementById('q-bonne-multiple-field').classList.toggle('hidden', type !== 'qcm_multiple');
    document.getElementById('q-a').required = type !== 'ouverte';
    document.getElementById('q-b').required = type !== 'ouverte';
}
qTypeSelect.addEventListener('change', updateQuestionTypeFields);

function openQuestionModal(id) {
    const modalMsg = document.getElementById('question-modal-msg');
    modalMsg.textContent = '';
    questionForm.reset();
    document.getElementById('q-id').value = '';
    document.getElementById('q-points').value = 1;
    document.getElementById('q-examen').checked = false;
    document.getElementById('q-actif').checked = true;
    document.getElementById('q-type').value = 'qcm_unique';
    document.getElementById('q-image-preview').classList.add('hidden');
    document.getElementById('q-remove-image').checked = false;
    document.getElementById('question-modal-title').textContent = 'Nouvelle question';
    questionForm.querySelectorAll('.q-bonne-multi').forEach(cb => cb.checked = false);

    if (id) {
        const q = questions.find(x => String(x.id) === String(id));
        if (q) {
            document.getElementById('question-modal-title').textContent = 'Modifier la question';
            document.getElementById('q-id').value = q.id;
            document.getElementById('q-categorie').value = q.categorie;
            document.getElementById('q-type').value = q.type;
            document.getElementById('q-points').value = q.points;
            document.getElementById('q-enonce').value = q.enonce;
            document.getElementById('q-a').value = q.options.a || '';
            document.getElementById('q-b').value = q.options.b || '';
            document.getElementById('q-c').value = q.options.c || '';
            document.getElementById('q-d').value = q.options.d || '';
            document.getElementById('q-examen').checked = q.examen_uniquement;
            document.getElementById('q-actif').checked = q.actif;

            if (q.type === 'qcm_unique') {
                document.getElementById('q-bonne').value = q.bonne_reponse || 'a';
            } else if (q.type === 'qcm_multiple') {
                const letters = (q.bonne_reponse || '').split(',');
                questionForm.querySelectorAll('.q-bonne-multi').forEach(cb => cb.checked = letters.includes(cb.value));
            }

            if (q.image) {
                document.getElementById('q-image-preview').classList.remove('hidden');
                document.getElementById('q-image-preview-img').src = '../' + q.image;
            }
        }
    }
    updateQuestionTypeFields();
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

    const type = document.getElementById('q-type').value;
    let bonneReponse = '';
    if (type === 'qcm_unique') {
        bonneReponse = document.getElementById('q-bonne').value;
    } else if (type === 'qcm_multiple') {
        bonneReponse = Array.from(questionForm.querySelectorAll('.q-bonne-multi:checked')).map(cb => cb.value).join(',');
    }

    const formData = new FormData();
    const id = document.getElementById('q-id').value;
    if (id) formData.append('id', id);
    formData.append('categorie', document.getElementById('q-categorie').value);
    formData.append('type', type);
    formData.append('enonce', document.getElementById('q-enonce').value);
    formData.append('option_a', document.getElementById('q-a').value);
    formData.append('option_b', document.getElementById('q-b').value);
    formData.append('option_c', document.getElementById('q-c').value);
    formData.append('option_d', document.getElementById('q-d').value);
    formData.append('bonne_reponse', bonneReponse);
    formData.append('points', document.getElementById('q-points').value || 1);
    formData.append('examen_uniquement', document.getElementById('q-examen').checked ? '1' : '');
    formData.append('actif', document.getElementById('q-actif').checked ? '1' : '');
    formData.append('remove_image', document.getElementById('q-remove-image').checked ? '1' : '');
    const imageFile = document.getElementById('q-image').files[0];
    if (imageFile) formData.append('image', imageFile);

    try {
        const res = await fetch('../api/questions.php?action=save', { method: 'POST', body: formData });
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

// Convertit "YYYY-MM-DD HH:MM:SS" (serveur) <-> "YYYY-MM-DDTHH:MM" (datetime-local)
function toDatetimeLocal(value) {
    if (!value) return '';
    return value.replace(' ', 'T').slice(0, 16);
}
function fromDatetimeLocal(value) {
    if (!value) return '';
    return value.replace('T', ' ') + ':00';
}

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
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <h2>${escapeHtml(q.nom)}</h2>
                    <span class="pill ${q.type === 'examen' ? 'warn' : 'ok'}">${q.type === 'examen' ? 'Examen' : 'Entraînement'}</span>
                </div>
                <p>${escapeHtml(q.description || '')}</p>
                <div class="meta">
                    <span class="pill">${q.nombre_questions} question(s)</span>
                    <span class="pill">Note sur ${q.note_max}</span>
                    <span class="pill">Seuil : ${q.seuil_reussite}</span>
                    <span class="pill ${!q.suffisant ? 'warn' : 'ok'}">${q.questions_disponibles} question(s) disponible(s)</span>
                    ${q.repartition ? q.repartition.map(p => `<span class="pill ${p.disponible < p.nombre_questions ? 'warn' : ''}">${escapeHtml(p.categorie)} : ${p.disponible}/${p.nombre_questions}</span>`).join('') : ''}
                    ${q.duree_minutes ? `<span class="pill">${q.duree_minutes} min chrono</span>` : ''}
                    ${q.tentatives_max ? `<span class="pill">${q.tentatives_max} tentative(s) max</span>` : ''}
                    <span class="pill">${q.afficher_score ? 'Score affiché' : 'Score masqué'}</span>
                    ${q.ouverture_debut || q.ouverture_fin ? `<span class="pill">Ouvert ${q.ouverture_debut ? 'du ' + q.ouverture_debut.slice(0, 16).replace('T', ' ') : ''}${q.ouverture_fin ? ' au ' + q.ouverture_fin.slice(0, 16).replace('T', ' ') : ''}</span>` : ''}
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
const qzTypeSelect = document.getElementById('qz-type');

function updateQuizTypeFields() {
    const isExam = qzTypeSelect.value === 'examen';
    document.getElementById('qz-ouverture-fields').classList.toggle('hidden', !isExam);
    document.getElementById('qz-tentatives-field').classList.toggle('hidden', !isExam);
}
qzTypeSelect.addEventListener('change', updateQuizTypeFields);

// ---------- Répartition des questions par thématique ----------
const repartitionToggle = document.getElementById('qz-repartition-toggle');
const repartitionRows = document.getElementById('qz-repartition-rows');

function updateRepartitionTotal() {
    const total = Array.from(repartitionRows.querySelectorAll('.qz-rep-nombre'))
        .reduce((sum, input) => sum + (parseInt(input.value, 10) || 0), 0);
    document.getElementById('qz-repartition-total').textContent = `Total : ${total} question(s)`;
}

function addRepartitionRow(categorie = '', nombre = 1) {
    const row = document.createElement('div');
    row.className = 'field-row';
    row.style.alignItems = 'flex-end';
    row.innerHTML = `
        <div class="field"><label>Thématique</label><input type="text" class="qz-rep-categorie" list="qz-categories-datalist" value="${escapeHtml(categorie)}" placeholder="Ex. Sécurité"></div>
        <div class="field" style="max-width:140px;"><label>Nombre</label><input type="number" class="qz-rep-nombre" min="1" value="${nombre}"></div>
        <button type="button" class="secondary qz-rep-remove-btn" style="padding:10px 14px;">Retirer</button>
    `;
    row.querySelector('.qz-rep-nombre').addEventListener('input', updateRepartitionTotal);
    row.querySelector('.qz-rep-remove-btn').addEventListener('click', () => { row.remove(); updateRepartitionTotal(); });
    repartitionRows.appendChild(row);
    updateRepartitionTotal();
}

document.getElementById('qz-add-repartition-btn').addEventListener('click', () => addRepartitionRow());

function updateRepartitionToggleFields() {
    const useRepartition = repartitionToggle.checked;
    document.getElementById('qz-simple-fields').classList.toggle('hidden', useRepartition);
    document.getElementById('qz-repartition-fields').classList.toggle('hidden', !useRepartition);
    if (useRepartition && !repartitionRows.children.length) addRepartitionRow();
}
repartitionToggle.addEventListener('change', updateRepartitionToggleFields);

function openQuizModal(id) {
    const modalMsg = document.getElementById('quiz-modal-msg');
    modalMsg.textContent = '';
    quizForm.reset();
    document.getElementById('qz-id').value = '';
    document.getElementById('qz-type').value = 'entrainement';
    document.getElementById('qz-nombre').value = 10;
    document.getElementById('qz-notemax').value = 20;
    document.getElementById('qz-seuil').value = 10;
    document.getElementById('qz-duree').value = '';
    document.getElementById('qz-tentatives').value = '';
    document.getElementById('qz-ouverture-debut').value = '';
    document.getElementById('qz-ouverture-fin').value = '';
    document.getElementById('qz-afficher-score').checked = true;
    document.getElementById('qz-actif').checked = true;
    document.getElementById('quiz-modal-title').textContent = 'Nouveau questionnaire';
    repartitionRows.innerHTML = '';
    repartitionToggle.checked = false;

    if (id) {
        const q = quizzes.find(x => String(x.id) === String(id));
        if (q) {
            document.getElementById('quiz-modal-title').textContent = 'Modifier le questionnaire';
            document.getElementById('qz-id').value = q.id;
            document.getElementById('qz-nom').value = q.nom;
            document.getElementById('qz-desc').value = q.description || '';
            document.getElementById('qz-type').value = q.type;
            document.getElementById('qz-categorie').value = q.categorie_filtre || '';
            document.getElementById('qz-nombre').value = q.nombre_questions;
            document.getElementById('qz-notemax').value = q.note_max;
            document.getElementById('qz-seuil').value = q.seuil_reussite;
            document.getElementById('qz-duree').value = q.duree_minutes || '';
            document.getElementById('qz-tentatives').value = q.tentatives_max || '';
            document.getElementById('qz-ouverture-debut').value = toDatetimeLocal(q.ouverture_debut);
            document.getElementById('qz-ouverture-fin').value = toDatetimeLocal(q.ouverture_fin);
            document.getElementById('qz-afficher-score').checked = q.afficher_score;
            document.getElementById('qz-actif').checked = q.actif;

            if (q.repartition && q.repartition.length) {
                repartitionToggle.checked = true;
                q.repartition.forEach(p => addRepartitionRow(p.categorie, p.nombre_questions));
            }
        }
    }
    updateQuizTypeFields();
    updateRepartitionToggleFields();
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

    const repartition = repartitionToggle.checked
        ? Array.from(repartitionRows.querySelectorAll('.field-row')).map(row => ({
            categorie: row.querySelector('.qz-rep-categorie').value.trim(),
            nombre_questions: parseInt(row.querySelector('.qz-rep-nombre').value, 10) || 1,
        })).filter(p => p.categorie !== '')
        : [];

    if (repartitionToggle.checked && !repartition.length) {
        modalMsg.textContent = 'Ajoutez au moins une thématique avec un nom, ou désactivez la répartition par thématique';
        saveBtn.disabled = false;
        return;
    }

    const payload = {
        id: document.getElementById('qz-id').value || null,
        nom: document.getElementById('qz-nom').value,
        description: document.getElementById('qz-desc').value,
        type: document.getElementById('qz-type').value,
        categorie_filtre: document.getElementById('qz-categorie').value,
        nombre_questions: parseInt(document.getElementById('qz-nombre').value, 10) || 1,
        repartition,
        note_max: parseFloat(document.getElementById('qz-notemax').value) || 20,
        seuil_reussite: parseFloat(document.getElementById('qz-seuil').value) || 0,
        duree_minutes: document.getElementById('qz-duree').value || '',
        tentatives_max: document.getElementById('qz-tentatives').value || '',
        ouverture_debut: fromDatetimeLocal(document.getElementById('qz-ouverture-debut').value),
        ouverture_fin: fromDatetimeLocal(document.getElementById('qz-ouverture-fin').value),
        afficher_score: document.getElementById('qz-afficher-score').checked,
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
    if (!confirm('Supprimer ce questionnaire ? Les tentatives déjà archivées seront conservées.')) return;
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
const STATUT_LABELS = {
    en_cours: '<span class="pill">En cours</span>',
    terminee: '<span class="pill ok">Terminée</span>',
    expiree: '<span class="pill warn">Expirée</span>',
};

async function loadAttempts() {
    const tbody = document.getElementById('attempts-tbody');
    try {
        const res = await fetch('../api/quizzes.php?action=attempts');
        const attempts = await res.json();

        if (!attempts.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="color:var(--text-secondary);">Aucune tentative enregistrée pour le moment.</td></tr>`;
            return;
        }

        tbody.innerHTML = attempts.map(a => {
            let noteCell = '—';
            let resultCell = '—';
            if (!a.afficher_score) {
                noteCell = '<span class="pill">Masqué au candidat</span>';
            } else if (a.score !== null) {
                noteCell = `${a.score} / ${a.note_max}`;
                resultCell = a.reussi ? '<span class="pill ok">Réussi</span>' : '<span class="pill warn">Non validé</span>';
            }
            return `
            <tr>
                <td>${escapeHtml(a.quiz_nom)}</td>
                <td><span class="pill ${a.quiz_type === 'examen' ? 'warn' : 'ok'}">${a.quiz_type === 'examen' ? 'Examen' : 'Entraînement'}</span></td>
                <td>${escapeHtml(a.candidat)}</td>
                <td>${STATUT_LABELS[a.statut] || a.statut}</td>
                <td>${noteCell}</td>
                <td>${resultCell}</td>
                <td>${escapeHtml(a.started_at)}</td>
                <td>${escapeHtml(a.completed_at || '—')}</td>
            </tr>
        `;
        }).join('');
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="color:var(--danger);">Erreur de chargement des résultats</td></tr>`;
    }
}

document.getElementById('year').textContent = new Date().getFullYear();
checkSession();
