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
  - Résultats : archive de **toutes** les tentatives (entraînement et examen), avec statut (en cours / terminée / expirée), conservée même si le questionnaire ou les questions sont ensuite modifiés ou supprimés.

Aucune gestion de comptes candidats n'est prévue pour l'instant (à venir dans une prochaine itération) : les candidats renseignent simplement leur nom avant de démarrer, et ce nom sert d'identifiant pour la reprise de tentative et le comptage des tentatives sur les examens.

L'accès à l'espace admin est protégé par un compte unique (identifiant / mot de passe), créé lors de la première visite de `admin/index.php`, sur le même principe que l'espace de configuration de serveur-home.

### Fonctionnement du minuteur et des tentatives d'examen

- Au démarrage d'un questionnaire chronométré, l'heure de début et le tirage des questions sont figés côté serveur dans la tentative (table `tentatives`). Un candidat qui recharge la page et resaisit le même nom **reprend** sa tentative en cours (mêmes questions, temps restant recalculé) plutôt que d'en démarrer une nouvelle.
- Si le temps imparti est écoulé, la tentative est automatiquement soumise avec les réponses déjà données et marquée **expirée**.
- Le nombre de tentatives maximum (examen) compte les tentatives **terminées ou expirées** ; une tentative en cours ne consomme pas de crédit tant qu'elle n'est pas finalisée.
- Les questions ouvertes ne comptent jamais dans le total de points noté automatiquement : le score porte uniquement sur les QCM.

## Installation

Prérequis : PHP 8+ avec les extensions `pdo_sqlite` et `zip` (pour l'import `.xlsx`).

1. Déposer les fichiers sur un serveur PHP (Apache/Nginx + PHP-FPM, ou `php -S localhost:8000`).
2. Le dossier `data/` doit être accessible en écriture (base SQLite `data/quizz.sqlite` et `data/credentials.json`, tous deux auto-générés et non versionnés).
3. Le dossier `uploads/questions/` doit être accessible en écriture et servi publiquement (images jointes aux questions).
4. Ouvrir `admin/index.php` pour créer le compte admin, ajouter des questions (manuellement ou via import) et créer un premier questionnaire.
5. Les candidats se rendent sur `index.php` pour passer le questionnaire.

> Le schéma de la base a évolué (types de questions, examens, tentatives). Si une base `data/quizz.sqlite` existait déjà à partir d'une version précédente, supprimez-la avant de redémarrer : elle sera recréée automatiquement avec le nouveau schéma (les données précédentes ne sont pas migrées).

## Structure

```
index.php                 Espace candidat
admin/index.php            Espace admin (SPA)
admin/app.js               Logique JS de l'admin
api/auth.php                Auth admin (compte unique)
api/questions.php           CRUD + import questions (admin)
api/quizzes.php              CRUD questionnaires + archive des tentatives (admin)
api/attempt.php              Passage de questionnaire côté candidat (public) : liste, démarrage/reprise, soumission
includes/db.php               Connexion SQLite + schéma
includes/xlsx_reader.php       Lecteur .xlsx minimaliste sans dépendance
includes/uploads.php           Gestion des images jointes aux questions
assets/style.css               Charte graphique (reprise de serveur-home)
data/                          Base SQLite + credentials (non versionnés)
uploads/questions/             Images jointes aux questions (non versionnées)
```
