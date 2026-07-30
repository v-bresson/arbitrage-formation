# ArcheryOps Judging

Application web PHP + JS pour créer des QCM et faire passer des questionnaires de validation de la formation d'arbitre assistant.

## Fonctionnalités

- **Partie candidat** (`index.php`) : liste des questionnaires actifs (entraînement ou examen), saisie du nom, passage du questionnaire (questions piochées aléatoirement), minuteur visible si le questionnaire est chronométré, note finale (ou message générique si le score est masqué) et statut réussi/non validé.
- **Partie admin** (`admin/index.php`) :
  - Banque de questions : QCM à **réponse unique**, QCM à **réponses multiples**, ou **question ouverte** (réponse libre, non notée automatiquement — à relire manuellement dans l'onglet Résultats). Chaque question peut avoir une **image jointe**, une catégorie, un nombre de points, et être **réservée à l'examen**.
  - Import de questions en masse via fichier **CSV** ou **XLSX** (colonnes : `categorie, type, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points, examen_uniquement` — voir le modèle `data/modele_import_questions.csv`).
  - Questionnaires de deux types :
    - **Entraînement** : pioche uniquement parmi les questions non réservées à l'examen (aucune question d'examen ne peut y apparaître).
    - **Examen** : peut piocher dans toute la banque de questions, avec réglages spécifiques : **fenêtre d'ouverture** (date/heure de début et de fin), **durée chronométrée** (minuteur, auto-soumission à expiration), **nombre de tentatives maximum** par candidat, et **affichage ou masquage du score** au candidat à la fin.
  - Chaque questionnaire (entraînement ou examen) peut piocher soit un nombre global de questions dans une catégorie unique (ou toutes), soit une **répartition par thématique** (ex. 3 questions "Sécurité" + 2 "Scoring" + 5 "Règlement") : le nombre total de questions est alors la somme des thématiques, et le tirage respecte le nombre demandé par thématique.
  - Résultats : archive de **toutes** les tentatives (entraînement et examen), avec statut (en cours / terminée / expirée), conservée même si le questionnaire ou les questions sont ensuite modifiés ou supprimés.

Aucune gestion de comptes candidats n'est prévue pour l'instant (à venir dans une prochaine itération) : les candidats renseignent simplement leur nom avant de démarrer, et ce nom sert d'identifiant pour la reprise de tentative et le comptage des tentatives sur les examens.

L'accès à l'espace admin est protégé par un compte unique (identifiant / mot de passe), créé lors de la première visite de `admin/index.php`, sur le même principe que l'espace de configuration de serveur-home.

### Fonctionnement du minuteur et des tentatives d'examen

- Au démarrage d'un questionnaire chronométré, l'heure de début et le tirage des questions sont figés côté serveur dans la tentative (table `tentatives`). Un candidat qui recharge la page et resaisit le même nom **reprend** sa tentative en cours (mêmes questions, temps restant recalculé) plutôt que d'en démarrer une nouvelle.
- Si le temps imparti est écoulé, la tentative est automatiquement soumise avec les réponses déjà données et marquée **expirée**.
- Le nombre de tentatives maximum (examen) compte les tentatives **terminées ou expirées** ; une tentative en cours ne consomme pas de crédit tant qu'elle n'est pas finalisée.
- Les questions ouvertes ne comptent jamais dans le total de points noté automatiquement : le score porte uniquement sur les QCM.

## Installation

Prérequis : PHP 8+ avec les extensions `pdo_mysql` et `zip` (pour l'import `.xlsx`), et un serveur **MariaDB** (ou MySQL) accessible.

1. Créer la base et un utilisateur dédié :
   ```sql
   CREATE DATABASE archeryops_judging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'archeryops'@'%' IDENTIFIED BY 'un-mot-de-passe-solide';
   GRANT ALL PRIVILEGES ON archeryops_judging.* TO 'archeryops'@'%';
   FLUSH PRIVILEGES;
   ```
   Les tables (`questions`, `quizzes`, `tentatives`) sont créées automatiquement au premier appel à l'application (voir `includes/db.php`) — aucun script SQL à lancer à la main.
2. Copier `includes/db-config.sample.php` en `includes/db-config.php` et renseigner l'hôte, le nom de la base, l'utilisateur et le mot de passe. Ce fichier n'est pas versionné (voir `.gitignore`).
   Alternative en conteneur/hébergement mutualisé : définir les variables d'environnement `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (le fichier `db-config.php`, s'il existe, les complète/écrase).
3. Déposer les fichiers sur un serveur PHP (Apache/Nginx + PHP-FPM, ou `php -S localhost:8000`).
4. Le dossier `data/` doit être accessible en écriture (`data/credentials.json`, auto-généré et non versionné, pour le compte admin).
5. Le dossier `uploads/questions/` doit être accessible en écriture et servi publiquement (images jointes aux questions).
6. Ouvrir `admin/index.php` pour créer le compte admin, ajouter des questions (manuellement ou via import) et créer un premier questionnaire.
7. Les candidats se rendent sur `index.php` pour passer le questionnaire.

> Les versions précédentes de l'application utilisaient une base SQLite locale (`data/quizz.sqlite`). Ce fichier n'est plus utilisé et peut être supprimé ; les données n'ont pas été migrées automatiquement vers MariaDB.

## Structure

```
index.php                 Espace candidat
admin/index.php            Espace admin (SPA)
admin/app.js               Logique JS de l'admin
api/auth.php                Auth admin (compte unique)
api/questions.php           CRUD + import questions (admin)
api/quizzes.php              CRUD questionnaires + archive des tentatives (admin)
api/attempt.php              Passage de questionnaire côté candidat (public) : liste, démarrage/reprise, soumission
includes/db.php               Connexion MariaDB/MySQL (PDO) + création du schéma
includes/db-config.sample.php  Modèle de fichier de config DB (à copier en db-config.php)
includes/xlsx_reader.php       Lecteur .xlsx minimaliste sans dépendance
includes/uploads.php           Gestion des images jointes aux questions
assets/style.css               Charte graphique (reprise de serveur-home)
data/                          Identifiants admin (credentials.json, non versionné)
uploads/questions/             Images jointes aux questions (non versionnées)
```
