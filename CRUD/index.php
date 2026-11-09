<?php
/**
 * Page d'Accueil - Liste des Personnes
 * TP07 : CRUD PHP Personne
 */
require_once __DIR__ . '/config.php';

// Gestion de la recherche / filtrage optionnel
$search = clean_string($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM personne 
                           WHERE cin LIKE ? OR nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?
                           ORDER BY nom ASC, prenom ASC");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term, $term, $term]);
} else {
    $stmt = $pdo->query("SELECT * FROM personne ORDER BY date_creation DESC");
}
$personnes = $stmt->fetchAll();
$totalCount = count($personnes);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Personnes - CRUD PHP</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page">
        <!-- En-tête de la page -->
        <header class="page-header">
            <div>
                <h1>Annuaire des Personnes</h1>
                <p class="subtitle">TP07 : Architecture CRUD PHP sécurisée à table unique (personne)</p>
            </div>
            <a href="ajouter.php" class="btn">+ Ajouter une personne</a>
        </header>

        <!-- Message Flash (succès / erreur) -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Barre d'outils et recherche -->
        <div class="table-toolbar">
            <form method="GET" action="index.php" class="search-form">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par CIN, nom, email...">
                <button type="submit" class="btn btn-sm">Filtrer</button>
                <?php if ($search !== ''): ?>
                    <a href="index.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
                <?php endif; ?>
            </form>
            <div style="font-size: 0.88rem; color: #64748b; font-weight: 600;">
                <?= $totalCount ?> personne<?= $totalCount > 1 ? 's' : '' ?> enregistrée<?= $totalCount > 1 ? 's' : '' ?>
            </div>
        </div>

        <!-- Tableau des personnes -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>CIN</th>
                        <th>Nom & Prénom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Date Naissance</th>
                        <th>CV (Document)</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalCount > 0): ?>
                        <?php foreach ($personnes as $p): ?>
                            <tr>
                                <td>
                                    <span class="badge-cin"><?= htmlspecialchars($p['cin']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nom']) ?></strong> <?= htmlspecialchars($p['prenom']) ?>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($p['email']) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= htmlspecialchars($p['email']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($p['telephone']) ?></td>
                                <td>
                                    <?= !empty($p['date_naissance']) ? date('d/m/Y', strtotime($p['date_naissance'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['cv']) && file_exists(UPLOAD_DIR . $p['cv'])): ?>
                                        <a href="uploads/<?= rawurlencode($p['cv']) ?>" target="_blank" rel="noopener noreferrer" class="badge badge-pdf">
                                            📄 Consulter CV
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-none">Aucun CV</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-links" style="justify-content: flex-end;">
                                        <a href="modifier.php?cin=<?= urlencode($p['cin']) ?>" class="action-edit" title="Modifier cette personne">
                                            ✏️ Modifier
                                        </a>
                                        <form method="POST" action="supprimer.php" style="display:inline;" onsubmit="return confirm('Confirmez-vous la suppression définitive de <?= htmlspecialchars(addslashes($p['prenom'] . ' ' . $p['nom'])) ?> (CIN : <?= htmlspecialchars($p['cin']) ?>) ?');">
                                            <input type="hidden" name="cin" value="<?= htmlspecialchars($p['cin']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                            <button type="submit" class="action-delete" title="Supprimer cette personne">
                                                🗑️ Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <p>🔍 Aucune personne trouvée<?= $search !== '' ? ' pour ce critère de recherche' : '' ?>.</p>
                                    <?php if ($search === ''): ?>
                                        <a href="ajouter.php" class="btn" style="margin-top: 12px;">+ Ajouter votre première personne</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
