<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps Judging — Connexion</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="brand">
    <img src="assets/logo.png" alt="ArcheryOps Judging">
    <p class="subtitle" id="page-brand-subtitle">Connexion</p>
</div>

<!-- ================= ECRAN DE CONFIGURATION INITIALE ================= -->
<div id="setup-screen" class="page hidden">
    <form class="panel" id="setup-form">
        <input type="text" id="setup-username-input" placeholder="Identifiant" autocomplete="username" required autofocus minlength="3">
        <input type="password" id="setup-password-input" placeholder="Mot de passe (8 caractères min.)" autocomplete="new-password" required minlength="8">
        <input type="password" id="setup-password-confirm-input" placeholder="Confirme le mot de passe" autocomplete="new-password" required minlength="8">
        <button type="submit" id="setup-btn">Créer le compte administrateur</button>
        <div class="msg error" id="setup-error"></div>
    </form>
</div>

<!-- ================= ECRAN DE CONNEXION ================= -->
<div id="login-screen" class="page hidden">
    <form class="panel" id="login-form">
        <input type="text" id="username-input" placeholder="Identifiant" autocomplete="username" required autofocus>
        <input type="password" id="password-input" placeholder="Mot de passe" autocomplete="current-password" required>
        <button type="submit" id="login-btn">Se connecter</button>
        <div class="msg error" id="login-error"></div>
    </form>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps Judging</footer>

<script>
const screens = {
    setup: document.getElementById('setup-screen'),
    login: document.getElementById('login-screen'),
};

function showScreen(name) {
    Object.values(screens).forEach(s => s.classList.add('hidden'));
    screens[name].classList.remove('hidden');
}

async function checkSession() {
    try {
        const statusRes = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=status',
        });
        const statusData = await statusRes.json();
        if (!statusData.configured) {
            document.getElementById('page-brand-subtitle').textContent = 'Première connexion — crée le compte administrateur';
            showScreen('setup');
            return;
        }

        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (data.authenticated) {
            window.location.href = 'dashboard.php';
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
    const passwordConfirm = document.getElementById('setup-password-confirm-input').value;
    const errorEl = document.getElementById('setup-error');
    errorEl.textContent = '';

    if (password !== passwordConfirm) {
        errorEl.textContent = 'Les mots de passe ne correspondent pas';
        return;
    }

    const btn = document.getElementById('setup-btn');
    btn.disabled = true;
    btn.textContent = 'Création...';

    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=setup&username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password),
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            errorEl.textContent = data.message || 'Impossible de créer le compte';
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Créer le compte administrateur';
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
    btn.textContent = 'Connexion...';

    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=login&username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password),
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'dashboard.php';
        } else {
            errorEl.textContent = data.message || 'Identifiant ou mot de passe incorrect';
        }
    } catch (err) {
        errorEl.textContent = 'Erreur de connexion au serveur';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Se connecter';
    }
});

document.getElementById('year').textContent = new Date().getFullYear();
checkSession();
</script>

</body>
</html>
