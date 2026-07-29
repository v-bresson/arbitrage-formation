# ArcheryOps Judging

Application web PHP + JS pour créer des QCM et faire passer des questionnaires de validation de la formation d'arbitre assistant.

## Fonctionnalités

- **Partie candidat** (`index.php`) : liste des questionnaires actifs, saisie du nom, passage du questionnaire (questions piochées aléatoirement dans la base), note finale et statut réussi/non validé.
- **Partie admin** (`admin/index.php`) :
  - Banque de questions : création, modification, suppression, note (points) par question, catégorie, activation/désactivation.
  - Import de questions en masse via fichier **CSV** ou **XLSX** (colonnes : `categorie, enonce, option_a, option_b, option_c, option_d, bonne_reponse, points` — voir le modèle `data/modele_import_questions.csv`).
  - Questionnaires : nombre de questions piochées, note maximale, seuil de réussite, filtre par catégorie.
  - Résultats : historique des tentatives des candidats.

Aucune gestion de comptes candidats n'est prévue pour l'instant (à venir dans une prochaine itération) : les candidats renseignent simplement leur nom avant de démarrer.

L'accès à l'espace admin est protégé par un compte unique (identifiant / mot de passe), créé lors de la première visite de `admin/index.php`, sur le même principe que l'espace de configuration de serveur-home.

## Installation

Prérequis : PHP 8+ avec les extensions `pdo_sqlite` et `zip` (pour l'import `.xlsx`).

1. Déposer les fichiers sur un serveur PHP (Apache/Nginx + PHP-FPM, ou `php -S localhost:8000`).
2. Le dossier `data/` doit être accessible en écriture (base SQLite `data/quizz.sqlite` et `data/credentials.json`, tous deux auto-générés et non versionnés).
3. Ouvrir `admin/index.php` pour créer le compte admin, ajouter des questions (manuellement ou via import) et créer un premier questionnaire.
4. Les candidats se rendent sur `index.php` pour passer le questionnaire.

## Structure

```
index.php              Espace candidat
admin/index.php         Espace admin (SPA)
admin/app.js            Logique JS de l'admin
api/auth.php             Auth admin (compte unique)
api/questions.php        CRUD + import questions (admin)
api/quizzes.php           CRUD questionnaires + résultats (admin)
api/attempt.php           Passage de questionnaire côté candidat (public)
includes/db.php           Connexion SQLite + schéma
includes/xlsx_reader.php  Lecteur .xlsx minimaliste sans dépendance
assets/style.css          Charte graphique (reprise de serveur-home)
data/                     Base SQLite + credentials (non versionnés)
```
