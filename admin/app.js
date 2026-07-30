// ===================================================================
// Logique de l'espace admin : auth, CRUD questions (QCM unique/multiple/
// ouverte + image, réservation examen), import CSV/XLSX, CRUD
// questionnaires (entraînement/examen, chrono, fenêtre d'ouverture,
// tentatives max, affichage du score), consultation des résultats.
// ===================================================================

const screens = {
    denied: document.getElementById('denied-screen'),
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
// L'authentification se fait sur la page de connexion générale
// (../index.php) : ici on vérifie juste la session et le rôle.
async function checkSession() {
    try {
        const res = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) {
            window.location.href = '../index.php';
            return;
        }
        if (data.role !== 'admin') {
            showScreen('denied');
            return;
        }
        showScreen('admin');
        initAdmin();
    } catch (err) {
        window.location.href = '../index.php';
    }
}

document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
        await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout',
        });
    } catch (err) { /* on déconnecte quand même côté écran */ }
    window.location.href = '../index.php';
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
        if (btn.dataset.tab === 'tiles') loadTilesAdmin();
        if (btn.dataset.tab === 'users') loadUsers();
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

// ================= TUILES (DASHBOARD) =================
let tiles = [];
const TILE_TYPE_LABELS = { questionnaire: 'Questionnaires (module intégré)', lien: 'Lien' };

async function loadTilesAdmin() {
    const grid = document.getElementById('tiles-admin-grid');
    const msg = document.getElementById('tiles-admin-msg');
    try {
        const res = await fetch('../api/tiles.php?action=list_admin');
        tiles = await res.json();

        if (!tiles.length) {
            grid.innerHTML = '';
            msg.textContent = 'Aucune tuile configurée.';
            return;
        }
        msg.textContent = '';
        grid.innerHTML = tiles.map(t => `
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <h2>${escapeHtml(t.nom)}</h2>
                    <span class="pill">${TILE_TYPE_LABELS[t.type] || t.type}</span>
                </div>
                <p>${escapeHtml(t.description || '')}</p>
                <div class="meta">
                    <span class="pill">Ordre : ${t.ordre}</span>
                    ${t.admin_uniquement ? '<span class="pill warn">Réservée admin</span>' : ''}
                    <span class="pill ${t.actif ? 'ok' : ''}">${t.actif ? 'Active' : 'Inactive'}</span>
                </div>
                <div class="row-actions" style="margin-top:8px;">
                    <button type="button" class="secondary edit-tile-btn" data-id="${t.id}">Modifier</button>
                    <button type="button" class="danger delete-tile-btn" data-id="${t.id}">Supprimer</button>
                </div>
            </div>
        `).join('');

        grid.querySelectorAll('.edit-tile-btn').forEach(btn => btn.addEventListener('click', () => openTileModal(btn.dataset.id)));
        grid.querySelectorAll('.delete-tile-btn').forEach(btn => btn.addEventListener('click', () => deleteTile(btn.dataset.id)));
    } catch (err) {
        msg.textContent = 'Erreur de chargement des tuiles';
    }
}

const tileModal = document.getElementById('tile-modal-overlay');
const tileForm = document.getElementById('tile-form');
const tileTypeSelect = document.getElementById('tile-type');

function updateTileTypeFields() {
    document.getElementById('tile-url-field').classList.toggle('hidden', tileTypeSelect.value !== 'lien');
}
tileTypeSelect.addEventListener('change', updateTileTypeFields);

function openTileModal(id) {
    const modalMsg = document.getElementById('tile-modal-msg');
    modalMsg.textContent = '';
    tileForm.reset();
    document.getElementById('tile-id').value = '';
    document.getElementById('tile-type').value = 'lien';
    document.getElementById('tile-icone').value = 'info';
    document.getElementById('tile-ordre').value = 0;
    document.getElementById('tile-admin-uniquement').checked = false;
    document.getElementById('tile-actif').checked = true;
    document.getElementById('tile-modal-title').textContent = 'Nouvelle tuile';

    if (id) {
        const t = tiles.find(x => String(x.id) === String(id));
        if (t) {
            document.getElementById('tile-modal-title').textContent = 'Modifier la tuile';
            document.getElementById('tile-id').value = t.id;
            document.getElementById('tile-nom').value = t.nom;
            document.getElementById('tile-desc').value = t.description || '';
            document.getElementById('tile-type').value = t.type;
            document.getElementById('tile-url').value = t.url || '';
            document.getElementById('tile-icone').value = t.icone;
            document.getElementById('tile-admin-uniquement').checked = t.admin_uniquement;
            document.getElementById('tile-ordre').value = t.ordre;
            document.getElementById('tile-actif').checked = t.actif;
        }
    }
    updateTileTypeFields();
    tileModal.classList.remove('hidden');
}

document.getElementById('new-tile-btn').addEventListener('click', () => openTileModal(null));
document.getElementById('tile-cancel-btn').addEventListener('click', () => tileModal.classList.add('hidden'));
tileModal.addEventListener('click', e => { if (e.target === tileModal) tileModal.classList.add('hidden'); });

tileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const modalMsg = document.getElementById('tile-modal-msg');
    modalMsg.textContent = '';
    const saveBtn = document.getElementById('tile-save-btn');
    saveBtn.disabled = true;

    const payload = {
        id: document.getElementById('tile-id').value || null,
        nom: document.getElementById('tile-nom').value,
        description: document.getElementById('tile-desc').value,
        type: document.getElementById('tile-type').value,
        url: document.getElementById('tile-url').value,
        icone: document.getElementById('tile-icone').value,
        admin_uniquement: document.getElementById('tile-admin-uniquement').checked,
        ordre: parseInt(document.getElementById('tile-ordre').value, 10) || 0,
        actif: document.getElementById('tile-actif').checked,
    };

    try {
        const res = await fetch('../api/tiles.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            tileModal.classList.add('hidden');
            await loadTilesAdmin();
        } else {
            modalMsg.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        modalMsg.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

async function deleteTile(id) {
    if (!confirm('Supprimer cette tuile du dashboard ?')) return;
    try {
        const res = await fetch('../api/tiles.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.success) await loadTilesAdmin();
    } catch (err) {
        document.getElementById('tiles-admin-msg').textContent = 'Erreur de connexion au serveur';
    }
}

// ================= UTILISATEURS =================
let users = [];

async function loadUsers() {
    const tbody = document.getElementById('users-tbody');
    try {
        const res = await fetch('../api/users.php?action=list');
        users = await res.json();

        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="color:var(--text-secondary);">Aucun utilisateur.</td></tr>`;
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr>
                <td>${escapeHtml(u.username)}</td>
                <td><span class="pill ${u.role === 'admin' ? 'warn' : ''}">${u.role === 'admin' ? 'Administrateur' : 'Utilisateur'}</span></td>
                <td>${u.actif ? '<span class="pill ok">Actif</span>' : '<span class="pill">Inactif</span>'}</td>
                <td>${escapeHtml(u.created_at)}</td>
                <td class="row-actions">
                    <button type="button" class="secondary edit-user-btn" data-id="${u.id}">Modifier</button>
                    <button type="button" class="danger delete-user-btn" data-id="${u.id}">Supprimer</button>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.edit-user-btn').forEach(btn => btn.addEventListener('click', () => openUserModal(btn.dataset.id)));
        tbody.querySelectorAll('.delete-user-btn').forEach(btn => btn.addEventListener('click', () => deleteUser(btn.dataset.id)));
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:var(--danger);">Erreur de chargement des utilisateurs</td></tr>`;
    }
}

const userModal = document.getElementById('user-modal-overlay');
const userForm = document.getElementById('user-form');

function openUserModal(id) {
    const modalMsg = document.getElementById('user-modal-msg');
    modalMsg.textContent = '';
    userForm.reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-role').value = 'user';
    document.getElementById('user-actif').checked = true;
    document.getElementById('user-password').required = true;
    document.getElementById('user-password-label').textContent = 'Mot de passe (8 caractères min.)';
    document.getElementById('user-modal-title').textContent = 'Nouvel utilisateur';

    if (id) {
        const u = users.find(x => String(x.id) === String(id));
        if (u) {
            document.getElementById('user-modal-title').textContent = "Modifier l'utilisateur";
            document.getElementById('user-id').value = u.id;
            document.getElementById('user-username').value = u.username;
            document.getElementById('user-role').value = u.role;
            document.getElementById('user-actif').checked = u.actif;
            document.getElementById('user-password').required = false;
            document.getElementById('user-password-label').textContent = 'Nouveau mot de passe (laisser vide = inchangé)';
        }
    }
    userModal.classList.remove('hidden');
}

document.getElementById('new-user-btn').addEventListener('click', () => openUserModal(null));
document.getElementById('user-cancel-btn').addEventListener('click', () => userModal.classList.add('hidden'));
userModal.addEventListener('click', e => { if (e.target === userModal) userModal.classList.add('hidden'); });

userForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const modalMsg = document.getElementById('user-modal-msg');
    modalMsg.textContent = '';
    const saveBtn = document.getElementById('user-save-btn');
    saveBtn.disabled = true;

    const payload = {
        id: document.getElementById('user-id').value || null,
        username: document.getElementById('user-username').value,
        password: document.getElementById('user-password').value,
        role: document.getElementById('user-role').value,
        actif: document.getElementById('user-actif').checked,
    };

    try {
        const res = await fetch('../api/users.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            userModal.classList.add('hidden');
            await loadUsers();
        } else {
            modalMsg.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        modalMsg.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

async function deleteUser(id) {
    if (!confirm('Supprimer définitivement cet utilisateur ?')) return;
    try {
        const res = await fetch('../api/users.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.success) await loadUsers();
        else document.getElementById('users-msg').textContent = data.message || 'Erreur lors de la suppression';
    } catch (err) {
        document.getElementById('users-msg').textContent = 'Erreur de connexion au serveur';
    }
}

document.getElementById('year').textContent = new Date().getFullYear();
checkSession();
