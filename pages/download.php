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
?>
<h2 class="page-title">Télécharger Artisan_ND</h2>

<?php if (!$is_logged_in): ?>
    <div class="auth-required-message" style="background: linear-gradient(135deg, #fff5f0 0%, #ffe8d6 100%); border: 2px solid #d2691e; border-radius: 12px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(210, 105, 30, 0.15);">
        <div style="text-align: center;">
            <h3 style="color: #8b4513; margin-bottom: 15px; font-size: 1.5em;">🔒 Accès réservé aux membres</h3>
            <p style="color: #654321; font-size: 1.1em; margin-bottom: 20px;">
                Pour télécharger Artisan_ND, vous devez créer un compte gratuit sur notre plateforme.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo url('register'); ?>" class="cta-button" style="background: linear-gradient(135deg, #d2691e 0%, #cd853f 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s;">
                    Créer un compte gratuit
                </a>
                <a href="<?php echo url('login'); ?>" class="cta-button" style="background: linear-gradient(135deg, #654321 0%, #8b4513 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s;">
                    Se connecter
                </a>
            </div>
            <p style="color: #8b4513; margin-top: 20px; font-size: 0.95em;">
                ⚡ L'inscription est rapide et gratuite !
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="download-section">
    <div class="software-info">
        <h3>Version <?php echo $software_info['version']; ?></h3>
        <p><?php echo $software_info['description']; ?></p>
        
        <div class="download-info">
            <p><strong>Taille :</strong> 85 MB</p>
            <p><strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong>Système supporté :</strong> Windows</p>
        </div>
        
        <?php if ($is_logged_in): ?>
            <a href="<?php echo url('download_file'); ?>" class="cta-button">Télécharger maintenant (gratuit)</a>
        <?php else: ?>
            <button class="cta-button" disabled style="opacity: 0.6; cursor: not-allowed;">Télécharger maintenant (gratuit)</button>
            <p style="color: #8b4513; margin-top: 10px; font-size: 0.9em;">⚠️ Veuillez vous connecter ou créer un compte pour télécharger</p>
        <?php endif; ?>
        
        <div class="download-stats">
            <p><strong>📥 15,247 téléchargements </strong></p>
        </div>
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