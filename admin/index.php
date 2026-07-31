<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps - Arbitrage — Administration</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="has-fixed-header">

<!-- ================= ACCES REFUSE ================= -->
<div id="denied-screen" class="page hidden" style="padding-top:40px;">
    <div class="brand"><img src="../assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
    <div class="panel" style="align-items:center;text-align:center;">
        <p>Cet espace est réservé aux administrateurs.</p>
        <a href="../dashboard.php" class="btn" style="margin-top:10px;">Retour au dashboard</a>
    </div>
</div>

<!-- ================= DASHBOARD ADMIN ================= -->
<div id="admin-screen" class="page wide hidden">
    <header class="site-header">
        <div class="site-header-row">
            <div class="brand"><img src="../assets/logo.png" alt="ArcheryOps - Arbitrage"></div>
            <h1 style="flex:1;text-align:center;font-size:1.3rem;color:var(--text-primary);">Gestionnaire de formations</h1>
            <div style="display:flex;gap:14px;align-items:center;">
                <span id="welcome-msg" style="color:var(--text-secondary);font-size:0.9rem;"></span>
                <button type="button" class="secondary" id="logout-btn">Se déconnecter</button>
            </div>
        </div>
    </header>
    <nav class="breadcrumb"><div class="breadcrumb-row"><a href="../dashboard.php">Accueil</a><span class="sep">/</span><a href="#" id="admin-breadcrumb-root">Administration</a><span class="sep">/</span><span class="current" id="admin-breadcrumb-current">Dashboard</span></div></nav>
    <div class="header-spacer"></div>
    <script src="../assets/header-fix.js"></script>

    <div class="admin-layout">
        <nav class="admin-sidebar">
            <button type="button" class="sidebar-link active" data-tab="overview">Dashboard</button>

            <div class="sidebar-group open">
                <button type="button" class="sidebar-group-toggle">QCM Examen</button>
                <div class="sidebar-submenu">
                    <button type="button" class="sidebar-link" data-tab="questions">Banque de questions</button>
                    <button type="button" class="sidebar-link" data-tab="quizzes">QCM Examen</button>
                    <button type="button" class="sidebar-link" data-tab="attempts">Résultats</button>
                </div>
            </div>

            <button type="button" class="sidebar-link" data-tab="users">Comptes utilisateurs</button>
            <button type="button" class="sidebar-link" data-tab="candidats">Candidats</button>
            <button type="button" class="sidebar-link" data-tab="formateurs">Formateur</button>
            <button type="button" class="sidebar-link" data-tab="roles">Rôles</button>
            <button type="button" class="sidebar-link" data-tab="maintenance">Mise à jour système</button>
        </nav>

        <div class="admin-content">
            <!-- ---------- DASHBOARD (VUE D'ENSEMBLE) ---------- -->
            <div id="tab-overview" class="tab-panel">
                <div id="overview-db-migration-banner" class="panel hidden" style="border-color:var(--danger);margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <p style="font-weight:600;color:var(--danger);margin-bottom:4px;">Mise à jour de la base de données nécessaire</p>
                        <p id="overview-db-migration-text" style="color:var(--text-secondary);font-size:0.9rem;"></p>
                    </div>
                    <button type="button" class="danger" id="overview-db-migration-btn">Aller à la mise à jour</button>
                </div>
                <div class="grid">
                    <div class="card"><p style="color:var(--text-secondary);">Questions</p><h2 id="overview-questions-count" style="font-size:2rem;">—</h2></div>
                    <div class="card"><p style="color:var(--text-secondary);">QCM Examen</p><h2 id="overview-quizzes-count" style="font-size:2rem;">—</h2></div>
                    <div class="card"><p style="color:var(--text-secondary);">Tentatives enregistrées</p><h2 id="overview-attempts-count" style="font-size:2rem;">—</h2></div>
                    <div class="card" id="overview-users-card"><p style="color:var(--text-secondary);">Comptes utilisateurs</p><h2 id="overview-users-count" style="font-size:2rem;">—</h2></div>
                </div>
                <div class="panel" style="margin-top:24px;" id="overview-app-panel">
                    <h2 style="margin-top:0;">Application</h2>
                    <div class="meta">
                        <span class="pill" id="overview-version-pill">Version : —</span>
                        <span class="pill" id="overview-backup-count-pill">0 sauvegarde(s)</span>
                    </div>
                </div>
            </div>

            <!-- ---------- QCM EXAMEN > BANQUE DE QUESTIONS ---------- -->
            <div id="tab-questions" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Banque de questions</h2>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" id="new-question-btn">+ Nouvelle question</button>
                        <button type="button" class="secondary" id="import-btn">Importer (CSV / XLSX)</button>
                        <a class="btn secondary" href="../data/modele_import_questions.csv" download style="text-decoration:none;">Télécharger un modèle</a>
                    </div>
                    <select id="category-filter" style="max-width:220px;"><option value="">Toutes les catégories</option></select>
                </div>
                <div class="table-wrap panel" style="padding:0;">
                    <table>
                        <thead><tr><th>Catégorie</th><th>Type</th><th>Énoncé</th><th>Bonne réponse</th><th>Points</th><th>Actif</th><th></th></tr></thead>
                        <tbody id="questions-tbody"></tbody>
                    </table>
                </div>
                <p class="msg" id="questions-msg"></p>
            </div>

            <!-- ---------- QCM EXAMEN ---------- -->
            <div id="tab-quizzes" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">QCM Examen</h2>
                <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                    <button type="button" id="new-quiz-btn">+ Nouveau QCM Examen</button>
                </div>
                <div class="grid" id="quizzes-grid"></div>
                <p class="msg" id="quizzes-msg"></p>
            </div>

            <!-- ---------- QCM EXAMEN > RESULTATS ---------- -->
            <div id="tab-attempts" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Résultats</h2>
                <div class="table-wrap panel" style="padding:0;">
                    <table>
                        <thead><tr><th>QCM Examen</th><th>Candidat</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Début</th><th>Temps</th><th></th></tr></thead>
                        <tbody id="attempts-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- ---------- COMPTES UTILISATEURS ---------- -->
            <div id="tab-users" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Comptes utilisateurs</h2>
                <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                    <button type="button" id="new-user-btn">+ Nouvel utilisateur</button>
                </div>
                <div class="table-wrap panel" style="padding:0;">
                    <table>
                        <thead><tr><th>Identifiant</th><th>Nom</th><th>Club</th><th>Rôle</th><th>Actif</th><th>Créé le</th><th></th></tr></thead>
                        <tbody id="users-tbody"></tbody>
                    </table>
                </div>
                <p class="msg" id="users-msg"></p>
            </div>

            <!-- ---------- CANDIDATS ---------- -->
            <div id="tab-candidats" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Candidats</h2>
                <div class="table-wrap panel" style="padding:0;">
                    <table>
                        <thead><tr><th>Identifiant</th><th>Nom</th><th>Club</th><th>Niveau</th><th>Option</th><th>Formateurs référents</th><th>Actif</th><th></th></tr></thead>
                        <tbody id="candidats-tbody"></tbody>
                    </table>
                </div>
                <p class="msg" id="candidats-msg"></p>
            </div>

            <!-- ---------- FORMATEUR ---------- -->
            <div id="tab-formateurs" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Formateur</h2>
                <div class="table-wrap panel" style="padding:0;">
                    <table>
                        <thead><tr><th>Identifiant</th><th>Nom</th><th>Club</th><th>Rôle</th><th>Actif</th><th>Créé le</th><th></th></tr></thead>
                        <tbody id="formateurs-tbody"></tbody>
                    </table>
                </div>
                <p class="msg" id="formateurs-msg"></p>
            </div>

            <!-- ---------- ROLES ---------- -->
            <div id="tab-roles" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Rôles</h2>
                <p class="modal-hint" style="margin-bottom:16px;">Le groupe de droits par défaut de chaque rôle, par section admin. Super-Admin a toujours un accès total et n'est pas modifiable. Les rôles personnalisés peuvent être ajoutés et supprimés (tant qu'aucun compte ne les utilise) ; les rôles historiques (Candidat, Formateur, Membre CRA) restent, seules leurs permissions se modifient.</p>
                <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
                    <button type="button" id="new-role-btn">+ Nouveau rôle</button>
                </div>
                <div class="grid roles-grid" id="roles-grid"></div>
                <p class="msg" id="roles-msg"></p>
            </div>

            <!-- ---------- MISE A JOUR SYSTEME ---------- -->
            <div id="tab-maintenance" class="tab-panel hidden">
                <h2 style="margin-bottom:16px;">Mise à jour système</h2>
                <div class="panel" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;">Version de l'application</h2>
                    <div class="meta">
                        <span class="pill" id="maint-version-pill">Version : —</span>
                        <span class="pill" id="maint-backup-count-pill">0 sauvegarde(s)</span>
                    </div>
                </div>

                <div class="panel" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;">Base de données</h2>
                    <p class="modal-hint">Une mise à jour de code peut nécessiter une évolution de la base de données (nouvelle colonne, nouvelle table). Cette évolution n'est jamais appliquée automatiquement : elle apparaît ici et doit être lancée volontairement.</p>
                    <div id="maint-db-uptodate-msg" class="msg success hidden">La base de données est à jour.</div>
                    <div id="maint-db-pending" class="hidden">
                        <ul id="maint-db-pending-list" style="margin:10px 0;padding-left:20px;color:var(--text-secondary);"></ul>
                        <button type="button" class="danger" id="maint-db-migrate-btn">Lancer la mise à jour de la base de données</button>
                    </div>
                    <p class="msg" id="maint-db-migrate-msg"></p>
                </div>

                <div id="maint-github-card" class="panel hidden" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;">Mise à jour depuis GitHub</h2>
                    <p class="modal-hint">Vérifie la dernière release publiée sur le dépôt GitHub configuré (voir <code>includes/db-config.php</code>) et propose de l'appliquer directement (même sauvegarde automatique que la mise à jour par upload).</p>
                    <button type="button" class="secondary" id="maint-github-check-btn">Vérifier les mises à jour</button>
                    <div id="maint-github-result" style="margin-top:12px;"></div>
                </div>

                <div class="panel" style="margin-bottom:20px;">
                    <h2 style="margin-top:0;">Mettre à jour l'application</h2>
                    <p class="modal-hint">Déposez le fichier <code>.zip</code> de la nouvelle version. Une sauvegarde complète du code actuel est créée automatiquement avant toute modification ; la mise à jour est bloquée si la sauvegarde ne peut pas être réalisée intégralement. <code>includes/db-config.php</code>, <code>data/</code> et <code>uploads/questions/</code> (images des questions) ne sont jamais remplacés.</p>
                    <input type="file" id="maint-update-file" accept=".zip" style="margin-bottom:12px;">
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;"><input type="checkbox" id="maint-update-confirm" style="width:auto;"> Je comprends que cette opération va remplacer les fichiers de l'application.</label>
                    <button type="button" id="maint-update-btn">Lancer la mise à jour</button>
                    <p class="msg" id="maint-update-msg"></p>
                </div>

                <div class="panel" style="margin-bottom:20px;padding:0;">
                    <div style="padding:24px 24px 0;">
                        <h2 style="margin-top:0;">Sauvegardes de code</h2>
                        <p class="modal-hint">Une sauvegarde complète est créée automatiquement avant chaque mise à jour ; seules les 10 plus récentes sont conservées. Restaurer remplace les fichiers actuels par ceux de la sauvegarde choisie (hors chemins protégés).</p>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Fichier</th><th>Créée le</th><th>Taille</th><th></th></tr></thead>
                            <tbody id="maint-backups-tbody"></tbody>
                        </table>
                    </div>
                    <p class="msg" id="maint-backups-msg" style="padding:0 24px 16px;"></p>
                </div>

                <div class="panel" style="padding:0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:24px 24px 0;flex-wrap:wrap;gap:10px;">
                        <h2 style="margin:0;">Journal de maintenance</h2>
                        <div style="display:flex;gap:10px;">
                            <button type="button" class="secondary" id="maint-log-refresh-btn">Actualiser</button>
                            <button type="button" class="danger" id="maint-log-clear-btn">Vider le journal</button>
                        </div>
                    </div>
                    <div class="table-wrap" style="max-height:320px;overflow-y:auto;">
                        <table>
                            <tbody id="maint-log-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALE QUESTION ================= -->
<div id="question-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2 id="question-modal-title">Nouvelle question</h2>
        <form id="question-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="q-id">
            <div class="field-row">
                <div class="field"><label>Catégorie</label><input type="text" id="q-categorie" placeholder="Ex. Règlement, Sécurité..."></div>
                <div class="field">
                    <label>Type de question</label>
                    <select id="q-type">
                        <option value="qcm_unique">QCM — réponse unique</option>
                        <option value="qcm_multiple">QCM — réponses multiples</option>
                        <option value="ouverte">Question ouverte</option>
                    </select>
                </div>
                <div class="field" id="q-points-field"><label>Points</label><input type="number" id="q-points" min="1" value="1"></div>
            </div>
            <div class="field"><label>Énoncé</label><textarea id="q-enonce" rows="3" required></textarea></div>
            <div class="field">
                <label>Image jointe (optionnelle)</label>
                <input type="file" id="q-image" accept="image/png,image/jpeg,image/webp,image/gif">
                <div id="q-image-preview" class="hidden" style="margin-top:8px;">
                    <img id="q-image-preview-img" style="max-width:100%;max-height:160px;border-radius:8px;display:block;margin-bottom:6px;">
                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="q-remove-image" style="width:auto;"> Supprimer l'image existante</label>
                </div>
            </div>
            <div id="q-options-fields">
                <div id="q-options-list" style="display:flex;flex-direction:column;gap:10px;"></div>
                <button type="button" class="secondary" id="q-add-option-btn" style="align-self:flex-start;margin-top:8px;">+ Ajouter une réponse</button>
                <div class="field" id="q-bonne-unique-field" style="margin-top:12px;">
                    <label>Bonne réponse</label>
                    <select id="q-bonne"></select>
                </div>
                <div class="field" id="q-bonne-multiple-field" style="margin-top:12px;">
                    <label>Bonnes réponses (une ou plusieurs)</label>
                    <div id="q-bonne-multi-list" style="display:flex;gap:16px;flex-wrap:wrap;"></div>
                </div>
            </div>
            <p class="msg" id="q-ouverte-hint" style="display:none;">Les questions ouvertes ne sont pas notées automatiquement : les réponses des candidats seront consultables dans l'onglet Résultats pour correction manuelle.</p>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="q-actif" style="width:auto;" checked> Question active</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="question-cancel-btn">Annuler</button>
                <button type="submit" id="question-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="question-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE IMPORT ================= -->
<div id="import-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2>Importer des questions</h2>
        <p class="modal-hint">
            Fichier .csv ou .xlsx avec les colonnes : <code>categorie, type, enonce, option_a, option_b, option_c, option_d, option_e, option_f, bonne_reponse, points</code>.
            <code>type</code> vaut <code>qcm_unique</code> (défaut), <code>qcm_multiple</code> ou <code>ouverte</code>. <code>option_e</code> et <code>option_f</code> sont facultatives (2 à 6 réponses).
            <code>bonne_reponse</code> est une lettre (a-f) pour un QCM à réponse unique, ou une liste séparée par des virgules (ex. <code>a,c</code>) pour un QCM à réponses multiples ; ignoré pour les questions ouvertes.
            Les questions importées sont actives par défaut.
        </p>
        <form id="import-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="file" id="import-file-input" accept=".csv,.xlsx" required>
            <div class="modal-actions">
                <button type="button" class="secondary" id="import-cancel-btn">Annuler</button>
                <button type="submit" id="import-submit-btn">Importer</button>
            </div>
            <div class="msg" id="import-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE QCM EXAMEN ================= -->
<div id="quiz-modal-overlay" class="modal-overlay hidden">
    <div class="modal" style="max-width:720px;">
        <h2 id="quiz-modal-title">Nouveau QCM Examen</h2>
        <form id="quiz-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="qz-id">
            <div class="field"><label>Nom</label><input type="text" id="qz-nom" required></div>
            <div class="field"><label>Description</label><textarea id="qz-desc" rows="2"></textarea></div>

            <div class="field">
                <label>Méthode de sélection des questions</label>
                <select id="qz-selection-mode">
                    <option value="auto">Automatique (tirage dans la banque de questions)</option>
                    <option value="manuel">Manuelle (choisir les questions une à une dans la banque)</option>
                </select>
            </div>

            <div id="qz-auto-fields" style="display:flex;flex-direction:column;gap:12px;">
                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qz-repartition-toggle" style="width:auto;"> Répartir le nombre de questions par thématique</label>

                <div id="qz-simple-fields" class="field-row">
                    <div class="field"><label>Catégorie (laisser vide = toutes les catégories)</label><input type="text" id="qz-categorie" list="qz-categories-datalist"></div>
                    <div class="field"><label>Nombre de questions</label><input type="number" id="qz-nombre" min="1" value="10" required></div>
                </div>

                <div id="qz-repartition-fields" class="hidden" style="display:flex;flex-direction:column;gap:10px;">
                    <label>Thématiques et nombre de questions</label>
                    <div id="qz-repartition-rows" style="display:flex;flex-direction:column;gap:8px;"></div>
                    <button type="button" class="secondary" id="qz-add-repartition-btn" style="align-self:flex-start;">+ Ajouter une thématique</button>
                    <p class="msg" id="qz-repartition-total" style="color:var(--text-secondary);"></p>
                </div>
                <datalist id="qz-categories-datalist"></datalist>
            </div>

            <div id="qz-manuel-fields" class="hidden" style="display:flex;flex-direction:column;gap:10px;">
                <label>Questions sélectionnées (<span id="qz-manuel-count">0</span>)</label>
                <input type="text" id="qz-manuel-search" placeholder="Rechercher dans la banque (énoncé, catégorie)...">
                <div id="qz-manuel-list" class="table-wrap panel" style="padding:0;max-height:280px;overflow-y:auto;"></div>
            </div>

            <div class="field-row">
                <div class="field"><label>Note maximale</label><input type="number" id="qz-notemax" min="1" value="20" required></div>
                <div class="field"><label>Seuil de réussite</label><input type="number" id="qz-seuil" min="0" value="10" required></div>
            </div>
            <div class="field-row">
                <div class="field"><label>Durée (minutes, laisser vide = pas de chrono)</label><input type="number" id="qz-duree" min="1" placeholder="Ex. 60"></div>
                <div class="field" id="qz-tentatives-field"><label>Tentatives max (laisser vide = illimité)</label><input type="number" id="qz-tentatives" min="1" placeholder="Ex. 2"></div>
            </div>
            <div class="field-row" id="qz-ouverture-fields">
                <div class="field"><label>Ouverture le</label><input type="datetime-local" id="qz-ouverture-debut"></div>
                <div class="field"><label>Fermeture le</label><input type="datetime-local" id="qz-ouverture-fin"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qz-afficher-score" style="width:auto;" checked> Afficher le score au candidat à la fin du QCM Examen</label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qz-actif" style="width:auto;" checked> QCM Examen actif (visible côté candidat)</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="quiz-cancel-btn">Annuler</button>
                <button type="submit" id="quiz-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="quiz-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE NOUVEAU ROLE ================= -->
<div id="role-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2>Nouveau rôle</h2>
        <form id="role-form" style="display:flex;flex-direction:column;gap:12px;">
            <div class="field"><label>Nom du rôle</label><input type="text" id="role-label" required placeholder="Ex. Arbitre stagiaire"></div>
            <p class="modal-hint" style="margin:0;">Les permissions du nouveau rôle sont initialisées à « Aucun accès » sur toutes les sections ; modifiez-les ensuite depuis sa carte.</p>
            <div class="modal-actions">
                <button type="button" class="secondary" id="role-cancel-btn">Annuler</button>
                <button type="submit" id="role-save-btn">Créer</button>
            </div>
            <div class="msg error" id="role-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE UTILISATEUR ================= -->
<div id="user-modal-overlay" class="modal-overlay hidden">
    <div class="modal" style="max-width:720px;">
        <h2 id="user-modal-title">Nouvel utilisateur</h2>
        <form id="user-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="user-id">
            <div class="field"><label>Identifiant</label><input type="text" id="user-username" required minlength="3"></div>
            <div class="field-row">
                <div class="field"><label id="user-password-label">Mot de passe (8 caractères min.)</label><input type="password" id="user-password" autocomplete="new-password"></div>
                <div class="field"><label id="user-password-confirm-label">Vérification du mot de passe</label><input type="password" id="user-password-confirm" autocomplete="new-password"><span class="msg error" id="user-password-match-msg" style="margin-top:4px;"></span></div>
            </div>
            <div class="field-row">
                <div class="field"><label>Prénom</label><input type="text" id="user-prenom"></div>
                <div class="field"><label>Nom</label><input type="text" id="user-nom"></div>
            </div>
            <div class="field-row">
                <div class="field"><label>Email</label><input type="text" id="user-email"></div>
                <div class="field"><label>Téléphone</label><input type="text" id="user-telephone"></div>
            </div>
            <div class="field-row">
                <div class="field"><label>N° de licence</label><input type="text" id="user-numero-licence"></div>
                <div class="field"><label>Club</label><input type="text" id="user-club"></div>
            </div>
            <div class="field">
                <label>Rôles (un compte peut cumuler plusieurs rôles)</label>
                <div id="user-roles-checkboxes" style="display:flex;flex-wrap:wrap;gap:14px;"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="user-actif" style="width:auto;" checked> Compte actif</label>

            <div id="user-training-field" style="display:flex;flex-direction:column;gap:12px;">
                <label style="margin:0;">Suivi de formation</label>
                <div class="field-row">
                    <div class="field">
                        <label>Niveau de formation</label>
                        <select id="user-niveau-formation">
                            <option value="">—</option>
                            <option value="Assistant Arbitre">Assistant Arbitre</option>
                            <option value="Arbitre Fédéral">Arbitre Fédéral</option>
                            <option value="Arbitre Duel">Arbitre Duel</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Option</label>
                        <select id="user-option-pratique">
                            <option value="">—</option>
                            <option value="Cible">Cible</option>
                            <option value="Nat/3D">Nat/3D</option>
                            <option value="Campagne">Campagne</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Formateurs référents (un ou plusieurs)</label>
                    <div id="user-formateurs-checkboxes" style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow-y:auto;padding:4px;"></div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label>Date d'entrée en formation</label>
                        <input type="date" id="user-date-entree-formation">
                    </div>
                </div>
            </div>

            <div class="modal-actions" style="justify-content:center;">
                <button type="button" class="secondary" id="user-cancel-btn">Annuler</button>
                <button type="submit" id="user-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="user-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE CORRECTION D'UNE TENTATIVE ================= -->
<div id="grade-modal-overlay" class="modal-overlay hidden">
    <div class="modal" style="max-width:760px;">
        <h2 id="grade-modal-title">Relecture / correction</h2>
        <p class="modal-hint" id="grade-modal-meta" style="margin:0;"></p>
        <div id="grade-questions-list" style="display:flex;flex-direction:column;gap:14px;margin-top:10px;"></div>
        <div class="panel" style="margin-top:6px;flex-direction:row;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <p class="modal-hint" style="margin:0;">Note recalculée</p>
                <p id="grade-total" style="font-weight:600;font-size:1.2rem;">—</p>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin:0;"><input type="checkbox" id="grade-publier" style="width:auto;"> Publier la note au candidat</label>
        </div>
        <div class="modal-actions">
            <button type="button" class="secondary" id="grade-cancel-btn">Fermer</button>
            <button type="button" id="grade-save-btn">Enregistrer la correction</button>
        </div>
        <div class="msg error" id="grade-modal-msg"></div>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps - Arbitrage — Administration</footer>

<script src="../assets/mvvm.js"></script>
<script src="app.js"></script>
</body>
</html>
