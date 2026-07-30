<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps Judging — Administration</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<!-- ================= ACCES REFUSE ================= -->
<div id="denied-screen" class="page hidden">
    <div class="brand"><img src="../assets/logo.png" alt="ArcheryOps Judging"></div>
    <div class="panel" style="align-items:center;text-align:center;">
        <p>Cet espace est réservé aux administrateurs.</p>
        <a href="../dashboard.php" class="btn" style="margin-top:10px;">Retour au dashboard</a>
    </div>
</div>

<!-- ================= DASHBOARD ADMIN ================= -->
<div id="admin-screen" class="page wide hidden">
    <div class="top-bar">
        <div class="brand" style="text-align:left;margin-bottom:0;">
            <img src="../assets/logo.png" alt="ArcheryOps Judging">
        </div>
        <div style="display:flex;gap:14px;align-items:center;">
            <div class="tabs">
                <button type="button" class="tab-btn active" data-tab="questions">Questions</button>
                <button type="button" class="tab-btn" data-tab="quizzes">Questionnaires</button>
                <button type="button" class="tab-btn" data-tab="attempts">Résultats</button>
                <button type="button" class="tab-btn" data-tab="tiles">Tuiles</button>
                <button type="button" class="tab-btn" data-tab="users">Utilisateurs</button>
                <button type="button" class="tab-btn" data-tab="maintenance">Maintenance</button>
            </div>
            <a href="../dashboard.php" class="secondary btn">Dashboard</a>
            <button type="button" class="secondary" id="logout-btn">Se déconnecter</button>
        </div>
    </div>

    <!-- ---------- ONGLET QUESTIONS ---------- -->
    <div id="tab-questions" class="tab-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" id="new-question-btn">+ Nouvelle question</button>
                <button type="button" class="secondary" id="import-btn">Importer (CSV / XLSX)</button>
            </div>
            <select id="category-filter" style="max-width:220px;"><option value="">Toutes les catégories</option></select>
        </div>
        <div class="table-wrap panel" style="padding:0;">
            <table>
                <thead><tr><th>Catégorie</th><th>Type</th><th>Énoncé</th><th>Bonne réponse</th><th>Points</th><th>Examen</th><th>Actif</th><th></th></tr></thead>
                <tbody id="questions-tbody"></tbody>
            </table>
        </div>
        <p class="msg" id="questions-msg"></p>
    </div>

    <!-- ---------- ONGLET QUESTIONNAIRES ---------- -->
    <div id="tab-quizzes" class="tab-panel hidden">
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button type="button" id="new-quiz-btn">+ Nouveau questionnaire</button>
        </div>
        <div class="grid" id="quizzes-grid"></div>
        <p class="msg" id="quizzes-msg"></p>
    </div>

    <!-- ---------- ONGLET RESULTATS ---------- -->
    <div id="tab-attempts" class="tab-panel hidden">
        <div class="table-wrap panel" style="padding:0;">
            <table>
                <thead><tr><th>Questionnaire</th><th>Type</th><th>Candidat</th><th>Statut</th><th>Note</th><th>Résultat</th><th>Début</th><th>Fin</th></tr></thead>
                <tbody id="attempts-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ---------- ONGLET TUILES ---------- -->
    <div id="tab-tiles" class="tab-panel hidden">
        <p class="modal-hint" style="margin-bottom:16px;">Les tuiles s'affichent sur le dashboard de tous les utilisateurs connectés (sauf celles réservées aux admins). La tuile "Questionnaires" donne accès au module de questionnaires intégré ; les autres peuvent pointer vers un lien (futur module ArcheryOps, ou URL externe).</p>
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button type="button" id="new-tile-btn">+ Nouvelle tuile</button>
        </div>
        <div class="grid" id="tiles-admin-grid"></div>
        <p class="msg" id="tiles-admin-msg"></p>
    </div>

    <!-- ---------- ONGLET UTILISATEURS ---------- -->
    <div id="tab-users" class="tab-panel hidden">
        <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
            <button type="button" id="new-user-btn">+ Nouvel utilisateur</button>
        </div>
        <div class="table-wrap panel" style="padding:0;">
            <table>
                <thead><tr><th>Identifiant</th><th>Rôle</th><th>Actif</th><th>Créé le</th><th></th></tr></thead>
                <tbody id="users-tbody"></tbody>
            </table>
        </div>
        <p class="msg" id="users-msg"></p>
    </div>

    <!-- ---------- ONGLET MAINTENANCE ---------- -->
    <div id="tab-maintenance" class="tab-panel hidden">
        <div class="panel" style="margin-bottom:20px;">
            <h2 style="margin-top:0;">Version de l'application</h2>
            <div class="meta">
                <span class="pill" id="maint-version-pill">Version : —</span>
                <span class="pill" id="maint-backup-count-pill">0 sauvegarde(s)</span>
            </div>
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
                <div class="field-row">
                    <div class="field"><label>Réponse A</label><input type="text" id="q-a"></div>
                    <div class="field"><label>Réponse B</label><input type="text" id="q-b"></div>
                </div>
                <div class="field-row">
                    <div class="field"><label>Réponse C (optionnelle)</label><input type="text" id="q-c"></div>
                    <div class="field"><label>Réponse D (optionnelle)</label><input type="text" id="q-d"></div>
                </div>
                <div class="field" id="q-bonne-unique-field">
                    <label>Bonne réponse</label>
                    <select id="q-bonne">
                        <option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option>
                    </select>
                </div>
                <div class="field" id="q-bonne-multiple-field">
                    <label>Bonnes réponses (une ou plusieurs)</label>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;">
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" class="q-bonne-multi" value="a" style="width:auto;"> A</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" class="q-bonne-multi" value="b" style="width:auto;"> B</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" class="q-bonne-multi" value="c" style="width:auto;"> C</label>
                        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" class="q-bonne-multi" value="d" style="width:auto;"> D</label>
                    </div>
                </div>
            </div>
            <p class="msg" id="q-ouverte-hint" style="display:none;">Les questions ouvertes ne sont pas notées automatiquement : les réponses des candidats seront consultables dans l'onglet Résultats pour correction manuelle.</p>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="q-examen" style="width:auto;"> Réservée à l'examen (exclue des questionnaires d'entraînement)</label>
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
            Fichier .csv ou .xlsx avec les colonnes : <code>categorie, type, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, examen_uniquement</code>.
            <code>type</code> vaut <code>qcm_unique</code> (défaut), <code>qcm_multiple</code> ou <code>ouverte</code>.
            <code>bonne_reponse</code> est une lettre (a-d) pour un QCM à réponse unique, ou une liste séparée par des virgules (ex. <code>a,c</code>) pour un QCM à réponses multiples ; ignoré pour les questions ouvertes.
            <code>examen_uniquement</code> vaut 1/oui pour réserver la question à l'examen. Les questions importées sont actives par défaut.
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

<!-- ================= MODALE QUESTIONNAIRE ================= -->
<div id="quiz-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2 id="quiz-modal-title">Nouveau questionnaire</h2>
        <form id="quiz-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="qz-id">
            <div class="field"><label>Nom</label><input type="text" id="qz-nom" required></div>
            <div class="field"><label>Description</label><textarea id="qz-desc" rows="2"></textarea></div>
            <div class="field">
                <label>Type de questionnaire</label>
                <select id="qz-type">
                    <option value="entrainement">Entraînement (n'utilise jamais les questions réservées à l'examen)</option>
                    <option value="examen">Examen (peut utiliser toute la banque de questions)</option>
                </select>
            </div>
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
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qz-afficher-score" style="width:auto;" checked> Afficher le score au candidat à la fin du questionnaire</label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qz-actif" style="width:auto;" checked> Questionnaire actif (visible côté candidat)</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="quiz-cancel-btn">Annuler</button>
                <button type="submit" id="quiz-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="quiz-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE TUILE ================= -->
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
                        <option value="questionnaire">Questionnaires (module intégré)</option>
                        <option value="lien">Lien (URL)</option>
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
            <div class="field"><label>Ordre d'affichage</label><input type="number" id="tile-ordre" value="0"></div>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="tile-actif" style="width:auto;" checked> Tuile active</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="tile-cancel-btn">Annuler</button>
                <button type="submit" id="tile-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="tile-modal-msg"></div>
        </form>
    </div>
</div>

<!-- ================= MODALE UTILISATEUR ================= -->
<div id="user-modal-overlay" class="modal-overlay hidden">
    <div class="modal">
        <h2 id="user-modal-title">Nouvel utilisateur</h2>
        <form id="user-form" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" id="user-id">
            <div class="field"><label>Identifiant</label><input type="text" id="user-username" required minlength="3"></div>
            <div class="field"><label id="user-password-label">Mot de passe (8 caractères min.)</label><input type="password" id="user-password" autocomplete="new-password"></div>
            <div class="field">
                <label>Rôle</label>
                <select id="user-role">
                    <option value="user">Utilisateur</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="user-actif" style="width:auto;" checked> Compte actif</label>
            <div class="modal-actions">
                <button type="button" class="secondary" id="user-cancel-btn">Annuler</button>
                <button type="submit" id="user-save-btn">Enregistrer</button>
            </div>
            <div class="msg error" id="user-modal-msg"></div>
        </form>
    </div>
</div>

<footer>&copy; <span id="year"></span> ArcheryOps Judging — Administration</footer>

<script src="../assets/mvvm.js"></script>
<script src="app.js"></script>
</body>
</html>
