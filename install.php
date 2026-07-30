<?php
// ===================================================================
// Assistant d'installation : demande les identifiants MariaDB/MySQL,
// teste la connexion, crée la base si besoin, écrit
// includes/db-config.php, puis initialise le schéma (tables) en
// réutilisant get_db() de includes/db.php. Une fois terminé, redirige
// vers index.php (création du premier compte administrateur).
//
// Si l'application est déjà installée et fonctionnelle (db-config.php
// présent/variables DB_* définies ET connexion OK), cette page redirige
// directement vers index.php plutôt que de permettre une réinstallation
// accidentelle — sauf avec ?force=1, pour un dépannage volontaire.
// ===================================================================

require_once __DIR__ . '/includes/db.php';

$configFile = __DIR__ . '/includes/db-config.php';
$force = isset($_GET['force']);

if (!$force && qa_db_configured()) {
    try {
        get_db();
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        // Configuré mais la connexion échoue réellement : on laisse
        // l'assistant s'afficher pour permettre de corriger.
    }
}

$errors = [];
$success = false;
$values = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'archeryops_judging',
    'username' => 'archeryops',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        if ($key === 'password') continue;
        $values[$key] = trim($_POST[$key] ?? '');
    }
    $values['password'] = (string)($_POST['password'] ?? '');
    $createDb = !empty($_POST['create_db']);

    if ($values['host'] === '') $errors[] = "L'hôte est obligatoire";
    if ($values['database'] === '') $errors[] = 'Le nom de la base est obligatoire';
    if ($values['username'] === '') $errors[] = "L'identifiant de connexion est obligatoire";

    if (!is_writable(__DIR__ . '/includes')) {
        $errors[] = "Le dossier includes/ n'est pas accessible en écriture sur le serveur : impossible d'y créer db-config.php.";
    }

    if (!$errors) {
        try {
            if ($createDb) {
                $rootDsn = "mysql:host={$values['host']};port={$values['port']};charset=utf8mb4";
                $rootPdo = new PDO($rootDsn, $values['username'], $values['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $dbNameEscaped = str_replace('`', '', $values['database']);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameEscaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $dsn = "mysql:host={$values['host']};port={$values['port']};dbname={$values['database']};charset=utf8mb4";
            new PDO($dsn, $values['username'], $values['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'Connexion impossible : ' . $e->getMessage();
        }
    }

    if (!$errors) {
        $php = "<?php\nreturn [\n"
            . "    'host' => " . var_export($values['host'], true) . ",\n"
            . "    'port' => " . var_export($values['port'], true) . ",\n"
            . "    'database' => " . var_export($values['database'], true) . ",\n"
            . "    'username' => " . var_export($values['username'], true) . ",\n"
            . "    'password' => " . var_export($values['password'], true) . ",\n"
            . "];\n";

        if (file_put_contents($configFile, $php) === false) {
            $errors[] = "Échec de l'écriture de includes/db-config.php.";
        } else {
            try {
                get_db(); // crée les tables si besoin
                $success = true;
            } catch (Throwable $e) {
                $errors[] = 'La configuration a été enregistrée mais une erreur est survenue à la création des tables : ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps Judging — Installation</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="brand center">
    <img src="assets/logo.png" alt="ArcheryOps Judging">
    <p class="subtitle">Installation — connexion à la base de données</p>
</div>

<div class="page">
<?php if ($success): ?>
    <div class="panel" style="align-items:center;text-align:center;">
        <p class="msg success">Base de données connectée et tables initialisées avec succès.</p>
        <a href="index.php" class="btn" style="margin-top:10px;">Continuer vers la création du compte administrateur</a>
    </div>
<?php else: ?>
    <form class="panel" method="post">
        <?php if ($errors): ?>
            <div class="msg error"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
        <?php endif; ?>
        <p class="modal-hint">Renseigne les identifiants d'une base MariaDB/MySQL déjà créée (ou coche la case ci-dessous pour la créer automatiquement si le compte a le droit <code>CREATE</code>). Ces informations seront écrites dans <code>includes/db-config.php</code> sur le serveur.</p>

        <div class="field"><label>Hôte</label><input type="text" name="host" value="<?php echo htmlspecialchars($values['host']); ?>" required></div>
        <div class="field-row">
            <div class="field"><label>Port</label><input type="text" name="port" value="<?php echo htmlspecialchars($values['port']); ?>"></div>
            <div class="field"><label>Nom de la base</label><input type="text" name="database" value="<?php echo htmlspecialchars($values['database']); ?>" required></div>
        </div>
        <div class="field-row">
            <div class="field"><label>Utilisateur</label><input type="text" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" required></div>
            <div class="field"><label>Mot de passe</label><input type="password" name="password" value="<?php echo htmlspecialchars($values['password']); ?>"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="create_db" value="1" style="width:auto;"> Créer la base si elle n'existe pas encore (nécessite le droit CREATE)</label>

        <button type="submit">Tester la connexion et installer</button>
    </form>
<?php endif; ?>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage</footer>
<script>document.getElementById('year').textContent = new Date().getFullYear();</script>

</body>
</html>
