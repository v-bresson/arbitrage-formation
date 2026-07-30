<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Connexion</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="brand center">
    <img src="assets/logo.png" alt="ArcheryOps - Arbitrage">
    <p class="subtitle" id="page-brand-subtitle"></p>
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

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script src="assets/mvvm.js"></script>
<script>
// ---------- ViewModel ----------
// Seul état réactif de la page : quel écran afficher, le texte du
// sous-titre, et les messages d'erreur/état "occupé" des deux
// formulaires. La vue (bind()) se redessine seule dès qu'une de ces
// propriétés change — aucun appel manuel à une fonction de rendu.
const vm = qaReactive({
    screen: null, // 'setup' | 'login'
    subtitle: 'Connexion',
    setupError: '',
    setupBusy: false,
    loginError: '',
    loginBusy: false,
});

function bind() {
    document.getElementById('page-brand-subtitle').textContent = vm.subtitle;

    document.getElementById('setup-screen').classList.toggle('hidden', vm.screen !== 'setup');
    document.getElementById('login-screen').classList.toggle('hidden', vm.screen !== 'login');

    document.getElementById('setup-error').textContent = vm.setupError;
    const setupBtn = document.getElementById('setup-btn');
    setupBtn.disabled = vm.setupBusy;
    setupBtn.textContent = vm.setupBusy ? 'Création...' : 'Créer le compte administrateur';

    document.getElementById('login-error').textContent = vm.loginError;
    const loginBtn = document.getElementById('login-btn');
    loginBtn.disabled = vm.loginBusy;
    loginBtn.textContent = vm.loginBusy ? 'Connexion...' : 'Se connecter';
}
qaWatchEffect(bind);

// ---------- Méthodes du ViewModel ----------
async function checkSession() {
    try {
        const statusRes = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=status',
        });
        if (!statusRes.ok) {
            const errData = await statusRes.json().catch(() => ({}));
            if (errData.needs_install) { window.location.href = 'install.php'; return; }
            throw new Error(errData.message || 'Erreur serveur');
        }
        const statusData = await statusRes.json();
        if (!statusData.configured) {
            vm.subtitle = 'Première connexion — crée le compte administrateur';
            vm.screen = 'setup';
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
            vm.subtitle = 'Connexion';
            vm.screen = 'login';
        }
    } catch (err) {
        vm.screen = 'login';
    }
}

document.getElementById('setup-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('setup-username-input').value;
    const password = document.getElementById('setup-password-input').value;
    const passwordConfirm = document.getElementById('setup-password-confirm-input').value;
    vm.setupError = '';

    if (password !== passwordConfirm) {
        vm.setupError = 'Les mots de passe ne correspondent pas';
        return;
    }

    vm.setupBusy = true;
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
            vm.setupError = data.message || 'Impossible de créer le compte';
        }
    } catch (err) {
        vm.setupError = 'Erreur de connexion au serveur';
    } finally {
        vm.setupBusy = false;
    }
});

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username-input').value;
    const password = document.getElementById('password-input').value;
    vm.loginError = '';
    vm.loginBusy = true;

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
            vm.loginError = data.message || 'Identifiant ou mot de passe incorrect';
        }
    } catch (err) {
        vm.loginError = 'Erreur de connexion au serveur';
    } finally {
        vm.loginBusy = false;
    }
});

document.getElementById('year').textContent = new Date().getFullYear();
checkSession();
</script>

</body>
</html>
