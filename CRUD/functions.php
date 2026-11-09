<?php
/**
 * Fonctions Utilitaires, Sécurité et Validation
 * Application CRUD PHP Personne (TP07)
 */

// ============================================================================
// 1. GESTION DES SESSIONS & MESSAGES FLASH
// ============================================================================

/**
 * Définit un message flash dans la session.
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Récupère et supprime le message flash actuel.
 */
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================================
// 2. PROTECTION CSRF
// ============================================================================

/**
 * Génère ou récupère le jeton CSRF de la session.
 */
function get_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie l'authenticité du jeton CSRF soumis.
 */
function verify_csrf_token(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================================
// 3. VALIDATION ET NETTOYAGE DES ENTRÉES
// ============================================================================

/**
 * Nettoie une chaîne de caractères (trim + suppression caractères de contrôle).
 */
function clean_string(?string $val): string {
    return trim($val ?? '');
}

/**
 * Valide le Nom ou Prénom.
 * Règle : Non vide, 2 à 50 caractères, lettres (avec accents), tirets, apostrophes et espaces.
 */
function validate_nom_prenom(string $value, string $fieldLabel): array {
    $val = clean_string($value);
    if ($val === '') {
        return ['valid' => false, 'error' => "Le champ {$fieldLabel} est obligatoire.", 'value' => ''];
    }
    if (mb_strlen($val) < 2 || mb_strlen($val) > 50) {
        return ['valid' => false, 'error' => "Le champ {$fieldLabel} doit contenir entre 2 et 50 caractères.", 'value' => $val];
    }
    // Lettres latines, caractères accentués français, espaces, tirets et apostrophes
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/u", $val)) {
        return ['valid' => false, 'error' => "Le champ {$fieldLabel} ne doit contenir que des lettres, tirets ou espaces.", 'value' => $val];
    }
    return ['valid' => true, 'error' => null, 'value' => $val];
}

/**
 * Valide le numéro de CIN.
 * Format standard marocain : 1 ou 2 lettres majuscules suivies de 4 à 8 chiffres (ex: EE123456, AB12345, K987654).
 */
function validate_cin(string $value, PDO $pdo, bool $isUpdate = false, ?string $currentCin = null): array {
    $cin = strtoupper(clean_string($value));
    if ($cin === '') {
        return ['valid' => false, 'error' => "Le numéro de CIN est obligatoire.", 'value' => ''];
    }
    // Règle de syntaxe : 1 ou 2 lettres suivies de 4 à 8 chiffres
    if (!preg_match('/^[A-Z]{1,2}[0-9]{4,8}$/', $cin)) {
        return ['valid' => false, 'error' => "Le format du CIN est invalide (ex: EE123456, A12345). 1 à 2 lettres majuscules suivies de 4 à 8 chiffres.", 'value' => $cin];
    }

    // Vérification de l'unicité dans la base de données
    if (!$isUpdate) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personne WHERE cin = ?");
        $stmt->execute([$cin]);
        if ($stmt->fetchColumn() > 0) {
            return ['valid' => false, 'error' => "Ce numéro de CIN ({$cin}) est déjà enregistré pour une autre personne.", 'value' => $cin];
        }
    } else {
        // En cas de modification, vérifier si le nouveau CIN n'appartient pas déjà à un autre enregistrement
        if ($currentCin !== null && $cin !== $currentCin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM personne WHERE cin = ?");
            $stmt->execute([$cin]);
            if ($stmt->fetchColumn() > 0) {
                return ['valid' => false, 'error' => "Ce numéro de CIN ({$cin}) existe déjà pour un autre utilisateur.", 'value' => $cin];
            }
        }
    }

    return ['valid' => true, 'error' => null, 'value' => $cin];
}

/**
 * Valide l'adresse e-mail.
 */
function validate_email(string $value, PDO $pdo, bool $isUpdate = false, ?string $currentCin = null): array {
    $email = strtolower(clean_string($value));
    if ($email === '') {
        return ['valid' => false, 'error' => "L'adresse email est obligatoire.", 'value' => ''];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => "L'adresse email n'a pas un format valide (ex: utilisateur@domaine.com).", 'value' => $email];
    }
    if (strlen($email) > 100) {
        return ['valid' => false, 'error' => "L'adresse email ne doit pas dépasser 100 caractères.", 'value' => $email];
    }

    // Vérification de l'unicité de l'email
    $sql = "SELECT cin FROM personne WHERE email = ?";
    $params = [$email];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetch();

    if ($existing) {
        if (!$isUpdate || ($currentCin !== null && $existing['cin'] !== $currentCin)) {
            return ['valid' => false, 'error' => "Cette adresse email est déjà associée à une personne inscrite.", 'value' => $email];
        }
    }

    return ['valid' => true, 'error' => null, 'value' => $email];
}

/**
 * Valide le numéro de téléphone.
 * Accepte les formats marocains (06..., 07..., 05..., +212...) ou internationaux.
 */
function validate_telephone(string $value): array {
    $phone = clean_string($value);
    if ($phone === '') {
        return ['valid' => false, 'error' => "Le numéro de téléphone est obligatoire.", 'value' => ''];
    }
    // Nettoyage des espaces pour vérification
    $normalized = preg_replace('/[\s\-\.]/', '', $phone);
    // Format Maroc (+212 ou 0 suivi de 5, 6, 7 et 8 chiffres) ou international (+XX...)
    $pattern = '/^(?:(?:\+|00)212|0)[5-7]\d{8}$/';
    $generalPattern = '/^\+?[0-9]{8,15}$/';

    if (!preg_match($pattern, $normalized) && !preg_match($generalPattern, $normalized)) {
        return ['valid' => false, 'error' => "Le numéro de téléphone est invalide (ex: 0612345678 ou +212612345678).", 'value' => $phone];
    }

    return ['valid' => true, 'error' => null, 'value' => $phone];
}

/**
 * Valide la date de naissance.
 * Format requis : AAAA-MM-JJ, date réelle, non future, âge compris entre 14 et 120 ans.
 */
function validate_date_naissance(string $value, int $minAge = 14, int $maxAge = 120): array {
    $dateStr = clean_string($value);
    if ($dateStr === '') {
        return ['valid' => false, 'error' => "La date de naissance est obligatoire.", 'value' => ''];
    }

    $d = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$d || $d->format('Y-m-d') !== $dateStr) {
        return ['valid' => false, 'error' => "La date de naissance doit respecter le format AAAA-MM-JJ.", 'value' => $dateStr];
    }

    $today = new DateTime('today');
    if ($d > $today) {
        return ['valid' => false, 'error' => "La date de naissance ne peut pas être située dans le futur.", 'value' => $dateStr];
    }

    $age = $today->diff($d)->y;
    if ($age < $minAge) {
        return ['valid' => false, 'error' => "L'âge minimum requis est de {$minAge} ans (âge calculé : {$age} ans).", 'value' => $dateStr];
    }
    if ($age > $maxAge) {
        return ['valid' => false, 'error' => "Veuillez saisir une date de naissance réaliste (âge calculé : {$age} ans).", 'value' => $dateStr];
    }

    return ['valid' => true, 'error' => null, 'value' => $dateStr];
}

// ============================================================================
// 4. GESTION SÉCURISÉE DU TÉLÉVERSEMENT DU CV
// ============================================================================

/**
 * Valide et enregistre de manière sécurisée un fichier CV (format PDF).
 *
 * @param array $file Le tableau $_FILES['cv']
 * @param string $cin Le CIN de la personne pour lier le nom
 * @param string|null $existingFile Nom de l'ancien fichier à remplacer
 * @param bool $required Indique si le fichier est strictement obligatoire
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function handle_cv_upload(array $file, string $cin, ?string $existingFile = null, bool $required = true): array {
    // Si aucun fichier soumis
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required && empty($existingFile)) {
            return ['success' => false, 'filename' => null, 'error' => "Le fichier du CV (au format PDF) est obligatoire."];
        }
        // Pas de changement, on conserve l'ancien fichier
        return ['success' => true, 'filename' => $existingFile, 'error' => null];
    }

    // Gestion des erreurs de téléversement PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => "Le fichier dépasse la taille maximale autorisée par le serveur (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "Le fichier dépasse la taille maximale spécifiée dans le formulaire.",
            UPLOAD_ERR_PARTIAL    => "Le fichier n'a été que partiellement téléversé.",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant sur le serveur.",
            UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque.",
            UPLOAD_ERR_EXTENSION  => "Une extension PHP a arrêté le téléversement."
        ];
        $msg = $uploadErrors[$file['error']] ?? "Erreur inconnue lors du téléversement du fichier.";
        return ['success' => false, 'filename' => null, 'error' => $msg];
    }

    // Vérification de la taille (3 Mo max)
    if ($file['size'] > MAX_FILE_SIZE) {
        $maxMb = round(MAX_FILE_SIZE / (1024 * 1024), 1);
        return ['success' => false, 'filename' => null, 'error' => "La taille du CV ne doit pas dépasser {$maxMb} Mo."];
    }

    // Vérification de l'extension
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        return ['success' => false, 'filename' => null, 'error' => "Seuls les fichiers au format PDF (.pdf) sont autorisés pour le CV."];
    }

    // Vérification du type MIME réel (sécurité contre les faux fichiers renommés en .pdf)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mimeType !== 'application/pdf') {
            return ['success' => false, 'filename' => null, 'error' => "Le contenu du fichier n'est pas un document PDF valide (MIME détecté : {$mimeType})."];
        }
    }

    // Création du répertoire uploads s'il n'existe pas
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'filename' => null, 'error' => "Impossible de créer le dossier de stockage des uploads."];
        }
    }

    // Génération d'un nom de fichier unique et sécurisé (évite collisions et exécution)
    // Structure : cv_{CIN_SANITIZED}_{TIMESTAMP}_{TOKEN_RANDOM}.pdf
    $sanitizedCin = preg_replace('/[^A-Za-z0-9]/', '', $cin);
    $uniqueToken = bin2hex(random_bytes(6));
    $newFilename = "cv_{$sanitizedCin}_" . time() . "_{$uniqueToken}.pdf";
    $destination = UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => null, 'error' => "Erreur lors de l'enregistrement du fichier CV sur le serveur."];
    }

    // Supprimer l'ancien fichier s'il existait et que c'est un nouveau fichier
    if ($existingFile && $existingFile !== $newFilename) {
        $oldFilePath = UPLOAD_DIR . basename($existingFile);
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    return ['success' => true, 'filename' => $newFilename, 'error' => null];
}
