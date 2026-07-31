<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Mon compte</title>
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
<nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><span class="current">Mon compte</span></div></nav>
<div class="header-spacer"></div>
<script src="assets/header-fix.js"></script>

<div class="page" style="max-width:720px;">
    <form class="panel" id="account-form" style="text-align:left;">
        <h2 style="margin:0;">Informations personnelles</h2>
        <div class="field"><label>Identifiant</label><input type="text" id="acc-username" disabled></div>
        <div class="field-row">
            <div class="field"><label>Nom</label><input type="text" id="acc-nom"></div>
            <div class="field"><label>Prénom</label><input type="text" id="acc-prenom"></div>
        </div>
        <div class="field"><label>Email</label><input type="text" id="acc-email"></div>
        <div class="field-row">
            <div class="field"><label>Téléphone</label><input type="text" id="acc-telephone"></div>
            <div class="field"><label>N° de licence</label><input type="text" id="acc-licence"></div>
        </div>
        <div class="field"><label>Club</label><input type="text" id="acc-club"></div>

        <h2 style="margin:12px 0 0;">Ma formation en cours</h2>
        <p class="modal-hint" style="margin:0;">Ces informations sont renseignées par l'administrateur et ne sont pas modifiables ici.</p>
        <div class="field-row">
            <div class="field"><label>Formateur référent</label><input type="text" id="acc-formateur-referent" disabled></div>
            <div class="field"><label>Niveau de diplôme en cours</label><input type="text" id="acc-niveau-formation" disabled></div>
        </div>
        <div class="field"><label>Date d'entrée en formation</label><input type="text" id="acc-date-entree-formation" disabled></div>

        <h2 style="margin:12px 0 0;">Changer de mot de passe</h2>
        <p class="modal-hint" style="margin:0;">Laisser vide pour conserver le mot de passe actuel.</p>
        <div class="field"><label>Mot de passe actuel</label><input type="password" id="acc-current-password" autocomplete="current-password"></div>
        <div class="field"><label>Nouveau mot de passe (8 caractères min.)</label><input type="password" id="acc-new-password" autocomplete="new-password"></div>

        <div class="modal-actions" style="justify-content:flex-start;">
            <button type="submit" id="acc-save-btn">Enregistrer</button>
        </div>
        <div class="msg error" id="acc-error"></div>
        <div class="msg success" id="acc-success"></div>
    </form>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script>
async function init() {
    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) { window.location.href = 'index.php'; return; }
        document.getElementById('welcome-msg').textContent = `Connecté en tant que ${[data.prenom, data.nom].filter(Boolean).join(' ') || data.username}`;
    } catch (err) {
        window.location.href = 'index.php';
        return;
    }

    try {
        const res = await fetch('api/account.php?action=get');
        if (res.status === 401) { window.location.href = 'index.php'; return; }
        const data = await res.json();
        if (data.success) {
            const a = data.account;
            document.getElementById('acc-username').value = a.username || '';
            document.getElementById('acc-nom').value = a.nom || '';
            document.getElementById('acc-prenom').value = a.prenom || '';
            document.getElementById('acc-email').value = a.email || '';
            document.getElementById('acc-telephone').value = a.telephone || '';
            document.getElementById('acc-licence').value = a.numero_licence || '';
            document.getElementById('acc-club').value = a.club || '';
            document.getElementById('acc-formateur-referent').value = a.formateur_referent_nom || '—';
            document.getElementById('acc-niveau-formation').value = a.niveau_formation || '—';
            document.getElementById('acc-date-entree-formation').value = a.date_entree_formation
                ? new Date(a.date_entree_formation).toLocaleDateString('fr-FR') : '—';
        }
    } catch (err) { /* pas bloquant */ }
}

document.getElementById('account-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = document.getElementById('acc-error');
    const successEl = document.getElementById('acc-success');
    errorEl.textContent = '';
    successEl.textContent = '';
    const saveBtn = document.getElementById('acc-save-btn');
    saveBtn.disabled = true;

    const payload = {
        nom: document.getElementById('acc-nom').value,
        prenom: document.getElementById('acc-prenom').value,
        email: document.getElementById('acc-email').value,
        telephone: document.getElementById('acc-telephone').value,
        numero_licence: document.getElementById('acc-licence').value,
        club: document.getElementById('acc-club').value,
        current_password: document.getElementById('acc-current-password').value,
        new_password: document.getElementById('acc-new-password').value,
    };

    try {
        const res = await fetch('api/account.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            successEl.textContent = 'Informations enregistrées.';
            document.getElementById('acc-current-password').value = '';
            document.getElementById('acc-new-password').value = '';
        } else {
            errorEl.textContent = data.message || "Erreur lors de l'enregistrement";
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        saveBtn.disabled = false;
    }
});

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
