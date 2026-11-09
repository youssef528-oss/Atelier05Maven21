# Projet CRUD PHP Sécurisé & Optimisé (TP07 - Table `personne`)

Ce projet constitue une refonte complète et sécurisée du projet **CRUD_PHP**, en alignement strict avec les exigences du **TP07 (Architecture CRUD PHP à table unique : personne)**.

---

## 1. Diagnostic des Faiblesses du Projet Initial

| Domaine | Faiblesse initiale détectée | Risque / Impact | Solution apportée |
| :--- | :--- | :--- | :--- |
| **Sécurité (Upload)** | `move_uploaded_file` direct avec le nom original du client sans contrôle d'extension ni de type MIME. | **Exécution de code arbitraire à distance (RCE)** en téléversant un fichier `.php`. Écrasement de fichiers existants. | Vérification stricte de l'extension `.pdf`, du type MIME réel via `finfo` (`application/pdf`), renommage cryptographique unique (`cv_CIN_timestamp_token.pdf`), taille max 3 Mo, et fichier `.htaccess` bloquant l'exécution de scripts. |
| **Validation des données** | **Aucune validation côté serveur** dans `ajouter.php` et `modifier.php`. Données insérées brutes. | Injection de données corrompues, chaînes vides, numéros de téléphone invalides, dates futures, crash SQL en cas de CIN en double. | Suite complète de validation modulaire dans `functions.php` : regex pour CIN marocain, nom/prénom avec accents, validation email RFC, téléphone, validité de la date et de l'âge (14–120 ans). |
| **Unicité (Doublons)** | Aucune vérification de l'existence préalable du CIN ou de l'Email avant `INSERT`. | Erreur fatale MySQL non capturée (Duplicate Entry) exposée à l'utilisateur. | Vérification proactive de l'unicité du CIN et de l'Email en BDD avec messages d'erreurs clairs. |
| **CSRF** | Aucune protection CSRF sur les formulaires d'ajout, de modification ou de suppression. | Attaques Cross-Site Request Forgery (suppression ou modification non sollicitée). | Génération et vérification d'un jeton CSRF cryptographique par session (`get_csrf_token()`, `verify_csrf_token()`). |
| **Suppression (Méthode HTTP)** | Suppression directe via simple `GET` (`supprimer.php?id=...`). | Déclenchement accidentel par des robots d'indexation, préchargement du navigateur ou liens malveillants. | Suppression sécurisée par formulaire `POST` avec token CSRF et confirmation JavaScript interactive. |
| **Expérience utilisateur (UX)** | Perte intégrale des données saisies en cas d'erreur de formulaire. | Frustration utilisateur de devoir retaper l'intégralité du formulaire. | Formulaire persistant (« sticky form ») conservant les valeurs saisies et affichant des messages d'erreur ciblés sous chaque champ. |
| **Feedback utilisateur** | Redirections silencieuses sans indication du résultat de l'opération. | L'utilisateur ne sait pas si l'action a réussi ou échoué. | Système de notifications flash (`alert-success`, `alert-danger`, `alert-warning`) stockées en session. |
| **Architecture BDD** | Table `utilisateurs` non conforme au TP07 qui exige la table `personne` avec `cin` comme clé primaire. | Non-respect du sujet du TP et incohérence clé primaire. | Table `personne` créée avec `cin` en PRIMARY KEY, et vue de compatibilité `utilisateurs`. Script SQL complet fourni. |
| **Gestion du CV en modification** | Le fichier CV n'était ni affiché ni gérable dans `modifier.php`. | Impossibilité de mettre à jour le CV ou de visualiser le document existant. | Affichage du badge du CV actuel avec lien de prévisualisation directe, et possibilité de téléverser un nouveau CV (qui supprime proprement l'ancien fichier). |
| **Nettoyage des fichiers orphelins** | Les CV pouvaient rester sur le disque sans lien en BDD. | Saturation de l'espace disque serveur. | Suppression automatique du fichier PDF associé sur le disque lors de la suppression de la personne ou du remplacement du CV. |

---

## 2. Architecture des Fichiers

```text
CRUD_PHP/
├── config.php          # Connexion PDO sécurisée (UTF-8, options, try-catch) et session
├── functions.php       # Fonctions modulaires : CSRF, Flash, Validations, Upload sécurisé
├── index.php           # Page d'accueil : liste avec recherche, badges, CV et actions
├── ajouter.php         # Formulaire d'ajout sécurisé avec validation exhaustive
├── modifier.php        # Formulaire pré-rempli (CIN en lecture seule selon TP07)
├── supprimer.php       # Script de traitement de suppression (nettoie aussi le fichier PDF)
├── styles.css          # Design moderne, responsive, animations et retours visuels
├── database.sql        # Script SQL de création de la BDD, table personne et données de test
└── uploads/            # Répertoire sécurisé de stockage des CV (PDF)
    ├── .htaccess       # Interdit l'exécution de tout script (PHP, CGI, etc.)
    └── index.php       # Empêche le listage du contenu du répertoire
```

---

## 3. Détail des Règles de Validation Implémentées

1. **CIN (Clé Primaire)** :
   - Obligatoire, converti automatiquement en majuscules.
   - Format standard : 1 à 2 lettres majuscules suivies de 4 à 8 chiffres (ex: `EE123456`, `AB12345`).
   - Unicité contrôlée en base de données avant tout enregistrement.
   - En modification : champ strictement en **lecture seule** (`readonly`), conforme au TP07.

2. **Nom & Prénom** :
   - Obligatoires, entre 2 et 50 caractères.
   - Lettres alphabétiques (avec accents français : é, è, ê, à, â, î, ï, ô, û, ç), tirets, apostrophes et espaces. Pas de caractères spéciaux ou chiffres.

3. **Adresse E-mail** :
   - Obligatoire, format valide vérifié via `filter_var(..., FILTER_VALIDATE_EMAIL)`.
   - Contrôle d'unicité en base de données pour éviter les comptes en double.

4. **Téléphone** :
   - Obligatoire.
   - Formats acceptés : Format marocain (`06...`, `07...`, `05...`, `+212...`) ou international standard (8 à 15 chiffres).

5. **Date de Naissance** :
   - Obligatoire, format `AAAA-MM-JJ`.
   - Contrôle de cohérence de date calendrier réelle.
   - Interdiction formelle des dates dans le futur.
   - Contrôle de réalisme d'âge : minimum 14 ans, maximum 120 ans.

6. **Fichier CV** :
   - Obligatoire lors de l'ajout ; optionnel lors de la modification.
   - Extension strictement limitée à `.pdf`.
   - Type MIME contrôlé côté serveur (`finfo_file` vérifiant `application/pdf`).
   - Taille maximale bridée à 3 Mo.
   - Nommage aléatoire cryptographique pour empêcher toute collision ou injection de chemin (`cv_CIN_timestamp_token.pdf`).

---

## 4. Installation et Déploiement

### Étape 1 : Base de données
1. Ouvrez votre gestionnaire MySQL (phpMyAdmin, DBeaver, ou terminal MySQL).
2. Importez le fichier `database.sql` :
   ```sql
   mysql -u root -p < database.sql
   ```
   *Ce script crée la base `tp_php`, la table `personne`, la vue de compatibilité `utilisateurs`, et insère 3 enregistrements de test.*

### Étape 2 : Configuration
Si vos identifiants MySQL diffèrent de la configuration locale par défaut (`localhost`, `root`, sans mot de passe), ajustez les constantes dans `config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tp_php');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Étape 3 : Droits du dossier uploads
Assurez-vous que le serveur web possède les droits d'écriture sur le dossier `uploads/` :
```bash
chmod 755 uploads/
```

### Étape 4 : Lancement
Placez le dossier dans le répertoire racine de votre serveur web (ex: `htdocs` pour XAMPP, `www` pour WampServer) ou lancez le serveur intégré PHP :
```bash
php -S localhost:8000
```
Accédez à l'application dans votre navigateur : `http://localhost:8000/index.php`.
