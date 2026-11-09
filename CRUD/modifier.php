<?php
/**
 * Page de Modification d'une Personne
 * TP07 : CRUD PHP Personne (Étape 3)
 */
require_once __DIR__ . '/config.php';

// Récupération de l'identifiant (cin prioritaire selon TP07, avec fallback id)
$cinParam = clean_string($_GET['cin'] ?? $_GET['id'] ?? '');

if ($cinParam === '') {
    set_flash('danger', "Identifiant CIN manquant pour la modification.");
    header('Location: index.php');
    exit;
}

// Recherche de la personne existante
$stmt = $pdo->prepare("SELECT * FROM personne WHERE cin = ?");
$stmt->execute([$cinParam]);
$personne = $stmt->fetch();

if (!$personne) {
    set_flash('danger', "Aucun enregistrement trouvé pour le CIN : " . htmlspecialchars($cinParam));
    header('Location: index.php');
    exit;
}

$errors = [];
$formData = [
    'nom' => $personne['nom'],
    'prenom' => $personne['prenom'],
    'email' => $personne['email'],
    'telephone' => $personne['telephone'],
    'date_naissance' => $personne['date_naissance'],
    'cv' => $personne['cv']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Contrôle CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
    }

    // 2. Récupération des valeurs postées
    $formData['nom'] = $_POST['nom'] ?? '';
    $formData['prenom'] = $_POST['prenom'] ?? '';
    $formData['email'] = $_POST['email'] ?? '';
    $formData['telephone'] = $_POST['telephone'] ?? '';
    $formData['date_naissance'] = $_POST['date_naissance'] ?? '';

    // 3. Validation des champs modifiables
    $resNom = validate_nom_prenom($formData['nom'], 'Nom');
    if (!$resNom['valid']) {
        $errors['nom'] = $resNom['error'];
    }

    $resPrenom = validate_nom_prenom($formData['prenom'], 'Prénom');
    if (!$resPrenom['valid']) {
        $errors['prenom'] = $resPrenom['error'];
    }

    $resEmail = validate_email($formData['email'], $pdo, true, $personne['cin']);
    if (!$resEmail['valid']) {
        $errors['email'] = $resEmail['error'];
    }

    $resTel = validate_telephone($formData['telephone']);
    if (!$resTel['valid']) {
        $errors['telephone'] = $resTel['error'];
    }

    $resDate = validate_date_naissance($formData['date_naissance']);
    if (!$resDate['valid']) {
        $errors['date_naissance'] = $resDate['error'];
    }

    // 4. Gestion du fichier CV (Optionnel lors de la modification)
    $newCvFilename = $personne['cv'];
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
        $resCv = handle_cv_upload($_FILES['cv'], $personne['cin'], $personne['cv'], false);
        if (!$resCv['success']) {
            $errors['cv'] = $resCv['error'];
        } else {
            $newCvFilename = $resCv['filename'];
        }
    }

    // 5. Exécution de la mise à jour si aucune erreur
    if (empty($errors)) {
        try {
            $sql = "UPDATE personne 
                    SET nom = ?, prenom = ?, email = ?, telephone = ?, date_naissance = ?, cv = ? 
                    WHERE cin = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $resNom['value'],
                $resPrenom['value'],
                $resEmail['value'],
                $resTel['value'],
                $resDate['value'],
                $newCvFilename,
                $personne['cin']
            ]);

            set_flash('success', "Les informations de '{$resPrenom['value']} {$resNom['value']}' ont été mises à jour avec succès !");
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Erreur UPDATE personne : " . $e->getMessage());
            $errors['global'] = "Une erreur est survenue lors de la mise à jour des données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la Personne - <?= htmlspecialchars($personne['prenom'] . ' ' . $personne['nom']) ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page">
        <!-- En-tête -->
        <header class="page-header">
            <div>
                <h1>Modifier la Personne</h1>
                <p class="subtitle">Mise à jour du profil de <strong><?= htmlspecialchars($personne['prenom'] . ' ' . $personne['nom']) ?></strong> (CIN : <?= htmlspecialchars($personne['cin']) ?>)</p>
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
                ⚠️ Le formulaire contient des erreurs. Veuillez corriger les champs en rouge avant d'enregistrer.
            </div>
        <?php endif; ?>

        <!-- Formulaire de modification -->
        <div class="form-card">
            <form method="POST" action="modifier.php?cin=<?= urlencode($personne['cin']) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

                <div class="form-grid">
                    <!-- Champ CIN (Strictement readonly selon TP07) -->
                    <div class="form-group">
                        <label for="cin">
                            <span>CIN (Identifiant unique)</span>
                            <span style="font-size:0.75rem; color:#64748b; font-weight:normal;">Non modifiable</span>
                        </label>
                        <input type="text" id="cin" name="cin" value="<?= htmlspecialchars($personne['cin']) ?>" readonly>
                        <span class="field-hint">La clé primaire (CIN) ne peut pas être modifiée après création.</span>
                    </div>

                    <!-- Champ Nom -->
                    <div class="form-group">
                        <label for="nom">
                            <span>Nom de famille <span class="required">*</span></span>
                        </label>
                        <input type="text" id="nom" name="nom" 
                               value="<?= htmlspecialchars($formData['nom']) ?>" 
                               class="<?= isset($errors['nom']) ? 'has-error' : '' ?>"
                               required maxlength="50">
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
                               value="<?= htmlspecialchars($formData['prenom']) ?>" 
                               class="<?= isset($errors['prenom']) ? 'has-error' : '' ?>"
                               required maxlength="50">
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
                               value="<?= htmlspecialchars($formData['email']) ?>" 
                               class="<?= isset($errors['email']) ? 'has-error' : '' ?>"
                               required maxlength="100">
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
                               value="<?= htmlspecialchars($formData['telephone']) ?>" 
                               class="<?= isset($errors['telephone']) ? 'has-error' : '' ?>"
                               required maxlength="25">
                        <?php if (isset($errors['telephone'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['telephone']) ?></span>
                        <?php else: ?>
                            <span class="field-hint">Format marocain (06/07/05...) ou international.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Date de Naissance -->
                    <div class="form-group">
                        <label for="date_naissance">
                            <span>Date de Naissance <span class="required">*</span></span>
                        </label>
                        <input type="date" id="date_naissance" name="date_naissance" 
                               value="<?= htmlspecialchars($formData['date_naissance']) ?>" 
                               class="<?= isset($errors['date_naissance']) ? 'has-error' : '' ?>"
                               max="<?= date('Y-m-d') ?>" required>
                        <?php if (isset($errors['date_naissance'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['date_naissance']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Champ Fichier CV -->
                    <div class="form-group full-width">
                        <label for="cv">
                            <span>Mettre à jour le CV (Optionnel)</span>
                        </label>
                        <input type="file" id="cv" name="cv" accept=".pdf,application/pdf" 
                               class="<?= isset($errors['cv']) ? 'has-error' : '' ?>">
                        <?php if (isset($errors['cv'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['cv']) ?></span>
                        <?php endif; ?>

                        <!-- Affichage du CV existant -->
                        <?php if (!empty($personne['cv']) && file_exists(UPLOAD_DIR . $personne['cv'])): ?>
                            <div class="current-file-badge">
                                <span>📄 Fichier actuel : <strong><?= htmlspecialchars($personne['cv']) ?></strong></span>
                                <a href="uploads/<?= rawurlencode($personne['cv']) ?>" target="_blank" class="badge badge-pdf" style="margin-left: 8px;">
                                    Consulter le PDF
                                </a>
                            </div>
                        <?php else: ?>
                            <span class="field-hint">Aucun fichier CV actuellement enregistré. Laissez vide pour ne rien changer.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn">💾 Enregistrer les modifications</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
