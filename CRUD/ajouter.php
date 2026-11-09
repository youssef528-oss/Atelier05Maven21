<?php
/**
 * Page d'Ajout d'une Personne
 * TP07 : CRUD PHP Personne
 */
require_once __DIR__ . '/config.php';

$errors = [];
$old = [
    'cin' => '',
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'date_naissance' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
    }

    // 2. Récupération des valeurs
    $old['cin'] = $_POST['cin'] ?? '';
    $old['nom'] = $_POST['nom'] ?? '';
    $old['prenom'] = $_POST['prenom'] ?? '';
    $old['email'] = $_POST['email'] ?? '';
    $old['telephone'] = $_POST['telephone'] ?? '';
    $old['date_naissance'] = $_POST['date_naissance'] ?? '';

    // 3. Validation de chaque champ
    $resCin = validate_cin($old['cin'], $pdo, false);
    if (!$resCin['valid']) {
        $errors['cin'] = $resCin['error'];
    }

    $resNom = validate_nom_prenom($old['nom'], 'Nom');
    if (!$resNom['valid']) {
        $errors['nom'] = $resNom['error'];
    }

    $resPrenom = validate_nom_prenom($old['prenom'], 'Prénom');
    if (!$resPrenom['valid']) {
        $errors['prenom'] = $resPrenom['error'];
    }

    $resEmail = validate_email($old['email'], $pdo, false);
    if (!$resEmail['valid']) {
        $errors['email'] = $resEmail['error'];
    }

    $resTel = validate_telephone($old['telephone']);
    if (!$resTel['valid']) {
        $errors['telephone'] = $resTel['error'];
    }

    $resDate = validate_date_naissance($old['date_naissance']);
    if (!$resDate['valid']) {
        $errors['date_naissance'] = $resDate['error'];
    }

    // 4. Validation et téléversement du CV (Obligatoire à l'ajout)
    $cvFilename = null;
    $resCv = handle_cv_upload($_FILES['cv'] ?? [], $resCin['value'] ?? 'temp', null, true);
    if (!$resCv['success']) {
        $errors['cv'] = $resCv['error'];
    } else {
        $cvFilename = $resCv['filename'];
    }

    // 5. Enregistrement si aucune erreur
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO personne (cin, nom, prenom, email, telephone, date_naissance, cv) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $resCin['value'],
                $resNom['value'],
                $resPrenom['value'],
                $resEmail['value'],
                $resTel['value'],
                $resDate['value'],
                $cvFilename
            ]);

            set_flash('success', "La personne '{$resPrenom['value']} {$resNom['value']}' a été ajoutée avec succès !");
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            // Nettoyage du fichier en cas d'échec SQL
            if ($cvFilename && file_exists(UPLOAD_DIR . $cvFilename)) {
                @unlink(UPLOAD_DIR . $cvFilename);
            }
            error_log("Erreur INSERT personne : " . $e->getMessage());
            $errors['global'] = "Une erreur est survenue lors de l'enregistrement en base de données.";
        }
    } else {
        // En cas d'erreur de validation, supprimer le fichier CV si jamais il avait été téléversé
        if ($cvFilename && file_exists(UPLOAD_DIR . $cvFilename)) {
            @unlink(UPLOAD_DIR . $cvFilename);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Personne - CRUD PHP</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page">
        <!-- En-tête -->
        <header class="page-header">
            <div>
                <h1>Ajouter une Personne</h1>
                <p class="subtitle">Remplissez les informations ci-dessous. Tous les champs sont contrôlés et validés.</p>
            </div>
            <a href="index.php" class="btn btn-secondary">← Retour à la liste</a>
        </header>

        <!-- Message d'erreur globale -->
        <?php if (!empty($errors['global'])): ?>
            <div class="alert alert-danger">
                ⚠️ <?= htmlspecialchars($errors['global']) ?>
            </div>
        <?php elseif (!empty($errors)): ?>
            <div class="alert alert-danger">
                ⚠️ Le formulaire contient des erreurs. Veuillez corriger les champs signalés en rouge.
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout -->
        <div class="form-card">
            <form method="POST" action="ajouter.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <div class="form-grid">
                    <!-- Champ CIN -->
                    <div class="form-group">
                        <label for="cin">
                            <span>CIN (Carte d'Identité) <span class="required">*</span></span>
                        </label>
                        <input type="text" id="cin" name="cin" 
                               value="<?= htmlspecialchars($old['cin']) ?>" 
                               class="<?= isset($errors['cin']) ? 'has-error' : '' ?>"
                               placeholder="Ex: EE123456 ou AB98765" 
                               required maxlength="15" autocomplete="off">
                        <?php if (isset($errors['cin'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['cin']) ?></span>
                        <?php else: ?>
                            <span class="field-hint">1 à 2 lettres majuscules suivies de 4 à 8 chiffres (Clé primaire unique).</span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Nom -->
                    <div class="form-group">
                        <label for="nom">
                            <span>Nom de famille <span class="required">*</span></span>
                        </label>
                        <input type="text" id="nom" name="nom" 
                               value="<?= htmlspecialchars($old['nom']) ?>" 
                               class="<?= isset($errors['nom']) ? 'has-error' : '' ?>"
                               placeholder="Ex: El Alaoui" required maxlength="50">
                        <?php if (isset($errors['nom'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['nom']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Prénom -->
                    <div class="form-group">
                        <label for="prenom">
                            <span>Prénom <span class="required">*</span></span>
                        </label>
                        <input type="text" id="prenom" name="prenom" 
                               value="<?= htmlspecialchars($old['prenom']) ?>" 
                               class="<?= isset($errors['prenom']) ? 'has-error' : '' ?>"
                               placeholder="Ex: Youssef" required maxlength="50">
                        <?php if (isset($errors['prenom'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['prenom']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Email -->
                    <div class="form-group">
                        <label for="email">
                            <span>Adresse E-mail <span class="required">*</span></span>
                        </label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($old['email']) ?>" 
                               class="<?= isset($errors['email']) ? 'has-error' : '' ?>"
                               placeholder="Ex: nom.prenom@domaine.com" required maxlength="100">
                        <?php if (isset($errors['email'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Téléphone -->
                    <div class="form-group">
                        <label for="telephone">
                            <span>Numéro de Téléphone <span class="required">*</span></span>
                        </label>
                        <input type="tel" id="telephone" name="telephone" 
                               value="<?= htmlspecialchars($old['telephone']) ?>" 
                               class="<?= isset($errors['telephone']) ? 'has-error' : '' ?>"
                               placeholder="Ex: 0653535353 ou +212653535353" required maxlength="25">
                        <?php if (isset($errors['telephone'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['telephone']) ?></span>
                        <?php else: ?>
                            <span class="field-hint">Format marocain (06/07/05...) ou international avec indicatif.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Date de Naissance -->
                    <div class="form-group">
                        <label for="date_naissance">
                            <span>Date de Naissance <span class="required">*</span></span>
                        </label>
                        <input type="date" id="date_naissance" name="date_naissance" 
                               value="<?= htmlspecialchars($old['date_naissance']) ?>" 
                               class="<?= isset($errors['date_naissance']) ? 'has-error' : '' ?>"
                               max="<?= date('Y-m-d') ?>" required>
                        <?php if (isset($errors['date_naissance'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['date_naissance']) ?></span>
                        <?php else: ?>
                            <span class="field-hint">L'âge doit être supérieur ou égal à 14 ans.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Fichier CV -->
                    <div class="form-group full-width">
                        <label for="cv">
                            <span>Document CV (Fichier PDF uniquement) <span class="required">*</span></span>
                        </label>
                        <input type="file" id="cv" name="cv" accept=".pdf,application/pdf" 
                               class="<?= isset($errors['cv']) ? 'has-error' : '' ?>" required>
                        <?php if (isset($errors['cv'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['cv']) ?></span>
                        <?php else: ?>
                            <span class="field-hint">Fichier au format PDF exclusivement (taille maximale : 3 Mo).</span>
                        <?php endif; ?>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn">💾 Enregistrer la personne</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
