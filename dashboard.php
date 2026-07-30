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
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <div style="display:flex;gap:14px;align-items:center;">
                <span id="welcome-msg" style="color:var(--text-secondary);font-size:0.9rem;"></span>
                <button type="button" class="secondary" id="logout-btn">Se déconnecter</button>
            </div>
            <label id="tiles-manage-toggle-row" class="switch-toggle hidden">
                <span class="switch-toggle-label">Mode config</span>
                <span class="switch">
                    <input type="checkbox" id="tiles-manage-toggle">
                    <span class="switch-slider"></span>
                </span>
            </label>
        </div>
    </div>
</header>
<nav class="breadcrumb"><div class="breadcrumb-row"><span class="current">Dashboard</span></div></nav>
<div class="header-spacer"></div>
<script src="assets/header-fix.js"></script>

<div class="page wide">
    <div class="grid" id="spaces-grid"></div>

    <div id="custom-tiles-section" style="margin-top:32px;">
        <h2 style="margin-bottom:16px;">Raccourcis personnalisés</h2>
        <div class="grid" id="tiles-grid"></div>
        <p class="msg" id="tiles-msg"></p>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<!-- ================= MODALE TUILE (mode paramétrage) ================= -->
<div id="tile-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2 id="tile-modal-title">Nouvelle tuile</h2>
        <form id="tile-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="tile-id">
            <div class="field"><label>Nom</label><input type="text" id="tile-nom" required></div>
            <div class="field"><label>Description</label><textarea id="tile-desc" rows="2"></textarea></div>
            <div class="field-row">
                <div class="field">
                    <label>Type</label>
                    <select id="tile-type">
                        <option value="lien">Lien (URL)</option>
                        <option value="questionnaire">Candidats arbitres (module intégré)</option>
                    </select>
                </div>
                <div class="field">
                    <label>Icône</label>
                    <select id="tile-icone">
                        <option value="target">Cible</option>
                        <option value="trophy">Trophée</option>
                        <option value="clipboard">Presse-papier</option>
                        <option value="users">Utilisateurs</option>
                        <option value="lock">Cadenas</option>
                        <option value="wifi">Wifi</option>
                        <option value="info">Info</option>
                    </select>
                </div>
            </div>
            <div class="field" id="tile-url-field"><label>URL</label><input type="text" id="tile-url" placeholder="https:// ou /chemin"></div>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="tile-admin-uniquement" style="width:auto;"> Réservée aux administrateurs</label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="tile-actif" style="width:auto;" checked> Tuile active</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="tile-cancel-btn">Annuler</button>
                <button type="submit" id="tile-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="tile-modal-msg"></div>
        </form>
    </div>
</div>

<script src="assets/mvvm.js"></script>
<script>
const ICONS = {
    target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    trophy: '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3a2 2 0 0 1 0 4h-1"/><path d="M7 5H4a2 2 0 0 0 0 4h1"/>',
    clipboard: '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
    wifi: '<path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
};

function escapeHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ---------- ViewModel ----------
// En plus des espaces fixes (Espace candidat/Formateur/Administration/Mon
// compte, selon les permissions), le Dashboard peut afficher des tuiles de
// raccourci personnalisées (table `tiles`, scope="accueil"), gérables
// depuis le Mode config par les comptes ayant le droit "Tuiles" en gestion
// complète (Super-Admin par défaut) — même mécanisme que l'Espace candidat.
const vm = qaReactive({
    username: null,
    role: null,
    permissions: {},
    hasFormateurAccess: false,
    hasPureAdminAccess: false,
    tiles: null,
    tilesMsg: '',
    manageMode: false,
    adminTiles: null,
});

function canManageTiles() {
    return vm.role === 'super_admin' || vm.permissions.tiles === 'manage';
}

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
    spaces.push({ nom: 'Mon compte', description: 'Modifier vos informations personnelles et votre mot de passe.', href: 'account.php', icone: 'user' });

    document.getElementById('spaces-grid').innerHTML = spaces.map(s => `
        <a class="card" href="${escapeHtml(s.href)}" style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[s.icone]}</svg>
            </div>
            <h2>${escapeHtml(s.nom)}</h2>
            <p>${escapeHtml(s.description)}</p>
        </a>
    `).join('');

    document.getElementById('tiles-manage-toggle-row').classList.toggle('hidden', !canManageTiles());
    document.getElementById('tiles-manage-toggle').checked = vm.manageMode;

    const grid = document.getElementById('tiles-grid');
    const msg = document.getElementById('tiles-msg');
    const section = document.getElementById('custom-tiles-section');

    if (vm.manageMode && canManageTiles()) {
        section.classList.remove('hidden');
        msg.textContent = '';
        renderManageTiles(grid);
        return;
    }

    const tiles = vm.tiles ? [...vm.tiles] : [];
    section.classList.toggle('hidden', tiles.length === 0);
    msg.textContent = '';

    grid.innerHTML = tiles.map(t => {
        const href = t.type === 'questionnaire' ? 'quiz.php' : t.url;
        const target = t.type === 'lien' ? ' target="_blank" rel="noopener noreferrer"' : '';
        return `
        <a class="card" href="${escapeHtml(href)}"${target} style="text-decoration:none;align-items:center;text-align:center;">
            <div class="icon-wrap" style="width:48px;height:48px;border-radius:12px;background:rgba(91,141,239,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);margin-bottom:6px;">
                <svg viewBox="0 0 24 24" style="width:24px;height:24px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;">${ICONS[t.icone] || ICONS.info}</svg>
            </div>
            <h2>${escapeHtml(t.nom)}</h2>
            <p>${escapeHtml(t.description || '')}</p>
        </a>
    `;
    }).join('');
}
qaWatchEffect(bind);

// ---------- Générique : glisser-déposer pour réordonner une grille ----------
function enableDragReorder(grid, onReorder) {
    let dragged = null;
    grid.querySelectorAll('.draggable-card').forEach(card => {
        card.addEventListener('dragstart', () => {
            dragged = card;
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            grid.querySelectorAll('.draggable-card').forEach(c => c.classList.remove('drag-over'));
        });
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (card !== dragged) card.classList.add('drag-over');
        });
        card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.classList.remove('drag-over');
            if (!dragged || dragged === card) return;
            const cards = [...grid.querySelectorAll('.draggable-card')];
            const from = cards.indexOf(dragged);
            const to = cards.indexOf(card);
            if (from < to) card.after(dragged); else card.before(dragged);
            const newOrder = [...grid.querySelectorAll('.draggable-card')].map(c => c.dataset.key);
            onReorder(newOrder);
        });
    });
}

// ---------- Tuiles personnalisées (mode paramétrage, scope="accueil") ----------
const TILE_TYPE_LABELS = { questionnaire: 'Candidats arbitres (module intégré)', lien: 'Lien' };

function renderManageTiles(grid) {
    const tiles = vm.adminTiles || [];
    grid.innerHTML = tiles.map(t => `
        <div class="card tile-manage-card draggable-card" draggable="true" data-key="${t.id}" data-id="${t.id}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div class="drag-handle" title="Glisser pour réordonner">⠿</div>
                <h2 style="flex:1;">${escapeHtml(t.nom)}</h2>
                <span class="pill">${TILE_TYPE_LABELS[t.type] || t.type}</span>
            </div>
            <p>${escapeHtml(t.description || '')}</p>
            <div class="meta">
                ${t.admin_uniquement ? '<span class="pill warn">Réservée admin</span>' : ''}
                <span class="pill ${t.actif ? 'ok' : ''}">${t.actif ? 'Active' : 'Inactive'}</span>
            </div>
            <div class="tile-manage-actions">
                <button type="button" class="secondary edit-tile-btn" data-id="${t.id}">Modifier</button>
                <button type="button" class="danger delete-tile-btn" data-id="${t.id}">Supprimer</button>
            </div>
        </div>
    `).join('') + `<button type="button" class="tile-add-card" id="add-tile-card-btn">+ Ajouter une tuile</button>`;

    grid.querySelectorAll('.edit-tile-btn').forEach(btn => btn.addEventListener('click', () => openTileModal(btn.dataset.id)));
    grid.querySelectorAll('.delete-tile-btn').forEach(btn => btn.addEventListener('click', () => deleteTile(btn.dataset.id)));
    document.getElementById('add-tile-card-btn').addEventListener('click', () => openTileModal(null));

    enableDragReorder(grid, async (newOrderIds) => {
        const byId = Object.fromEntries(tiles.map(t => [String(t.id), t]));
        for (let i = 0; i < newOrderIds.length; i++) {
            const t = byId[newOrderIds[i]];
            if (t && t.ordre !== i) await saveTilePayload({ ...t, ordre: i });
        }
        await loadAdminTiles();
    });
}

async function loadAdminTiles() {
    try {
        const res = await fetch('api/tiles.php?action=list_admin&scope=accueil');
        if (res.status === 401 || res.status === 403) return;
        vm.adminTiles = await res.json();
    } catch (err) { /* pas bloquant */ }
}

async function saveTilePayload(payload) {
    const res = await fetch('api/tiles.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, scope: 'accueil' }),
    });
    return res.json();
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
    document.getElementById('tile-admin-uniquement').checked = false;
    document.getElementById('tile-actif').checked = true;
    document.getElementById('tile-modal-title').textContent = 'Nouvelle tuile';

    if (id) {
        const t = (vm.adminTiles || []).find(x => String(x.id) === String(id));
        if (t) {
            document.getElementById('tile-modal-title').textContent = 'Modifier la tuile';
            document.getElementById('tile-id').value = t.id;
            document.getElementById('tile-nom').value = t.nom;
            document.getElementById('tile-desc').value = t.description || '';
            document.getElementById('tile-type').value = t.type;
            document.getElementById('tile-url').value = t.url || '';
            document.getElementById('tile-icone').value = t.icone;
            document.getElementById('tile-admin-uniquement').checked = t.admin_uniquement;
            document.getElementById('tile-actif').checked = t.actif;
        }
    }
    updateTileTypeFields();
    tileModal.classList.remove('hidden');
}

document.getElementById('tile-cancel-btn').addEventListener('click', () => tileModal.classList.add('hidden'));
tileModal.addEventListener('click', e => { if (e.target === tileModal) tileModal.classList.add('hidden'); });

tileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const modalMsg = document.getElementById('tile-modal-msg');
    modalMsg.textContent = '';
    const saveBtn = document.getElementById('tile-save-btn');
    saveBtn.disabled = true;

    const id = document.getElementById('tile-id').value || null;
    const existing = id ? (vm.adminTiles || []).find(x => String(x.id) === String(id)) : null;
    const maxOrdre = (vm.adminTiles || []).reduce((max, t) => Math.max(max, t.ordre), 0);

    const payload = {
        id,
        nom: document.getElementById('tile-nom').value,
        description: document.getElementById('tile-desc').value,
        type: document.getElementById('tile-type').value,
        url: document.getElementById('tile-url').value,
        icone: document.getElementById('tile-icone').value,
        admin_uniquement: document.getElementById('tile-admin-uniquement').checked,
        ordre: existing ? existing.ordre : maxOrdre + 1,
        actif: document.getElementById('tile-actif').checked,
    };

    try {
        const data = await saveTilePayload(payload);
        if (data.success) {
            tileModal.classList.add('hidden');
            await loadAdminTiles();
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
        const res = await fetch('api/tiles.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        const data = await res.json();
        if (data.success) await loadAdminTiles();
    } catch (err) { /* pas bloquant */ }
}

document.getElementById('tiles-manage-toggle').addEventListener('change', async (e) => {
    vm.manageMode = e.target.checked;
    if (vm.manageMode) {
        if (!vm.adminTiles) await loadAdminTiles();
    } else {
        // Recharge les raccourcis affichés : sinon les modifications faites
        // en mode config (ajout/suppression/réordonnancement) n'apparaissent
        // qu'après un rechargement manuel de la page.
        await loadTiles();
    }
});

async function loadTiles() {
    try {
        const res = await fetch('api/tiles.php?action=list&scope=accueil');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        vm.tiles = await res.json();
    } catch (err) { /* pas bloquant */ }
}

// ---------- Méthodes du ViewModel ----------
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
        vm.role = data.role;
        vm.permissions = data.permissions || {};
        vm.hasFormateurAccess = !!data.has_formateur_access;
        vm.hasPureAdminAccess = !!data.has_pure_admin_access;

        await loadTiles();
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
