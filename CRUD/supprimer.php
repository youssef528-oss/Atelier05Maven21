<?php
/**
 * Script de Traitement de la Suppression
 * TP07 : CRUD PHP Personne (Étape 4)
 */
require_once __DIR__ . '/config.php';

// Récupération de l'identifiant (cin prioritaire selon TP07, avec fallback id)
$cin = clean_string($_POST['cin'] ?? $_GET['cin'] ?? $_GET['id'] ?? '');

// Vérification CSRF si la requête est transmise en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', "Erreur de sécurité : Jeton CSRF invalide lors de la tentative de suppression.");
        header('Location: index.php');
        exit;
    }
}

if ($cin === '') {
    set_flash('danger', "Identifiant CIN manquant pour exécuter la suppression.");
    header('Location: index.php');
    exit;
}

try {
    // 1. Recherche préalable pour supprimer le fichier CV associé
    $stmt = $pdo->prepare("SELECT nom, prenom, cv FROM personne WHERE cin = ?");
    $stmt->execute([$cin]);
    $personne = $stmt->fetch();

    if ($personne) {
        $fullName = $personne['prenom'] . ' ' . $personne['nom'];
        
        // Suppression sécurisée du fichier CV sur le disque s'il existe
        if (!empty($personne['cv'])) {
            $filePath = UPLOAD_DIR . basename($personne['cv']);
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        // 2. Suppression de l'enregistrement en base de données
        $deleteStmt = $pdo->prepare("DELETE FROM personne WHERE cin = ?");
        $deleteStmt->execute([$cin]);

        set_flash('success', "La personne '{$fullName}' (CIN : {$cin}) a été supprimée avec succès.");
    } else {
        set_flash('warning', "Aucune personne trouvée avec le CIN : {$cin}.");
    }
} catch (PDOException $e) {
    error_log("Erreur DELETE personne : " . $e->getMessage());
    set_flash('danger', "Erreur technique lors de la suppression de l'enregistrement.");
}

// Redirection immédiate vers la page d'accueil
header('Location: index.php');
exit;
