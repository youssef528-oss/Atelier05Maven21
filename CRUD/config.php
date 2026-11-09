<?php
/**
 * Configuration de la Base de Données et Initialisation de la Session
 * Application CRUD PHP Personne (TP07)
 */

// Démarrage de la session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Paramètres de connexion
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'tp_php');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Dossier de stockage des CV téléversés
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 3 * 1024 * 1024); // 3 Mo max

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Message convivial sans exposer les identifiants en production
    error_log("Erreur de connexion BDD : " . $e->getMessage());
    die("<div style='font-family:sans-serif; padding:20px; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin:20px auto; max-width:600px;'>
            <h3>Erreur de connexion à la base de données</h3>
            <p>Impossible d'établir la connexion avec MySQL. Veuillez vérifier que le serveur MySQL est démarré et que la base <strong>" . htmlspecialchars(DB_NAME) . "</strong> existe.</p>
         </div>");
}

// Inclusion des fonctions utilitaires
require_once __DIR__ . '/functions.php';
