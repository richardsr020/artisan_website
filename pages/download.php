<?php 
prevent_direct_access();

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la configuration du système d'abonnement pour vérifier l'authentification
require_once __DIR__ . '/../subscription/config.php';

// Vérifier si l'utilisateur est connecté
$is_logged_in = is_logged_in();

$software_list = [];
if ($is_logged_in) {
    $db = get_db_connection();
    $stmt = $db->query("SELECT * FROM software WHERE is_active = 1 ORDER BY created_at DESC");
    $software_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<h2 class="page-title">Télécharger Artisan_ND</h2>

<div class="download-section">
    <div class="software-info">
        <h3>Versions disponibles</h3>
        <p>Téléchargez la version souhaitée (y compris les anciennes versions).</p>

   
            <?php if (!empty($software_list)): ?>
                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;">Nom</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;">Version</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;">Taille</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;">Date</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;">Lien</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($software_list as $sw): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #f2f2f2;"><?php echo htmlspecialchars($sw['name']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #f2f2f2;"><?php echo htmlspecialchars($sw['version'] ?? ''); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #f2f2f2;"><?php echo number_format(($sw['file_size'] ?? 0) / 1024 / 1024, 2); ?> MB</td>
                                <td style="padding: 10px; border-bottom: 1px solid #f2f2f2;"><?php echo isset($sw['created_at']) ? date('d/m/Y H:i', strtotime($sw['created_at'])) : ''; ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #f2f2f2;">
                                    <a class="cta-button" style="padding: 8px 14px; font-size: 0.95rem;" href="<?php echo url('download_software', ['id' => $sw['id']]); ?>">Télécharger</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <a href= "../files/downloads/artisan_nd_v1.1.zip" class="cta-button" disabled style="">Télécharger </a>
            <?php endif; ?>
    </div>
    
    <div class="requirements">
        <h3>Configuration requise</h3>
        <ul>
            <?php foreach($software_info['system_requirements'] as $key => $value): ?>
                <li><strong><?php echo $key; ?>:</strong> <?php echo $value; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
