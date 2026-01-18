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
<h2 class="page-title">Système d'abonnement Partenaires</h2>

<div class="subscription-info">
    
    <div class="subscription-card premium">
        <div class="premium-badge">RECOMMANDÉ</div>
        <h3>👑 Partenaire Premium</h3>
        <h3>💼 Devenez Partenaire</h3>
        <div class="price"><?php echo number_format(RECHARGE_UNIT_PRICE, 2); ?>$<span style="font-size: 1rem; color: #666;"> / unité de recharge (quotas)</span></div>
        <p>Paiement mensuel avec tous les avantages:</p>
        <ul style="text-align: left; margin: 1rem 0;">
            <li>✅ Droits sur la comercialisation de quotas de reabonnement</li>
            <li>✅ Quotas de recharge illimités pour vos abonnées</li>
            <li>✅ Libre de choisir votre prix sur les recharges de vos abonné</li>
            <li>✅ Support technique prioritaire</li>
        </ul>
        <?php if ($is_logged_in): ?>
            <a href="<?php echo url('register'); ?>" class="btn btn-success">Devenir Partenaire Premium</a>
        <?php else: ?>
            <p style="text-align: center; margin-top: 1.5rem; color: #8b4513; font-size: 1.1em;">
                <a href="<?php echo url('login'); ?>" style="color: #d2691e; text-decoration: none; font-weight: 600; transition: color 0.3s;">
                    Se connecter pour devenir partenaire →
                </a>
            </p>
        <?php endif; ?>
    </div>
    
  
</div>