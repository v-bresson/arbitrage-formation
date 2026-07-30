<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArcheryOps Judging — Administration</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="brand" id="page-brand">
    <img src="../assets/logo.png" alt="ArcheryOps Judging">
    <p class="subtitle" id="page-brand-subtitle">Administration</p>
</div>

<!-- ================= ECRAN DE CONFIGURATION INITIALE ================= -->
<div id="setup-screen" class="page hidden">
    <form class="panel" id="setup-form">
        <input type="text" id="setup-username-input" placeholder="Identifiant" autocomplete="username" required autofocus minlength="3">
        <input type="password" id="setup-password-input" placeholder="Mot de passe (8 caractères min.)" autocomplete="new-password" required minlength="8">
        <input type="password" id="setup-password-confirm-input" placeholder="Confirme le mot de passe" autocomplete="new-password" required minlength="8">
        <button type="submit" id="setup-btn">Créer le compte admin</button>
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
    <p style="margin-top:16px;"><a href="../index.php">&larr; Retour à l'espace candidat</a></p>
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
            </div>
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

<footer>&copy; <span id="year"></span> ArcheryOps Judging — Administration</footer>

<script src="app.js"></script>
</body>
</html>
