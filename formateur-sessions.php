<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Sessions de formations</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="has-fixed-header">

<header class="site-header">
    <div class="site-header-row">
        <div class="brand"><img src="assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
        <h1 style="flex:1;text-align:center;font-size:1.3rem;color:var(--text-primary);">Gestionnaire de formations</h1>
    </div>
</header>
<nav class="breadcrumb"><div class="breadcrumb-row"><a href="dashboard.php">Accueil</a><span class="sep">/</span><a href="formateur.php">Espace formateur</a><span class="sep">/</span><span class="current">Sessions de formations</span></div></nav>
<div class="header-spacer"></div>
<script src="assets/header-fix.js"></script>

<div class="page wide">
    <div class="panel" style="align-items:center;text-align:center;">
        <h2 style="margin:0;">Sessions de formations</h2>
        <p class="modal-hint" style="margin:0;">Ce module (planification des sessions de formation) arrive bientôt.</p>
        <a href="formateur.php" class="btn" style="margin-top:10px;">Retour à l'Espace formateur</a>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>

<script>
(async function checkSession() {
    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check',
        });
        const data = await res.json();
        if (!data.authenticated) { window.location.href = 'index.php'; }
    } catch (err) {
        window.location.href = 'index.php';
    }
})();
document.getElementById('year').textContent = new Date().getFullYear();
</script>

</body>
</html>
