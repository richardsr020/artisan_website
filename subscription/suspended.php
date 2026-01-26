<?php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    header('Location: ' . LOGIN_PAGE);
    exit;
}

$db = get_db_connection();
$user_id = $_SESSION['user_id'];
$reason = $_GET['reason'] ?? '';

// Récupérer les informations de l'utilisateur
$user_stmt = $db->prepare("SELECT debt_suspended, last_payment_date FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

$is_debt_suspended = (int)($user_info['debt_suspended'] ?? 0) === 1;
$debt_amount = 0;
$total_units = 0;

if ($is_debt_suspended || $reason === 'debt') {
    // Calculer la dette depuis le dernier paiement
    $last_payment_date = $user_info['last_payment_date'] ?? null;
    if ($last_payment_date) {
        $units_stmt = $db->prepare("SELECT COALESCE(SUM(quota_units), 0) as total_units FROM recharges WHERE user_id = ? AND status = 'completed' AND recharge_date > ?");
        $units_stmt->execute([$user_id, $last_payment_date]);
    } else {
        // Si pas de paiement, calculer depuis toutes les recharges
        $units_stmt = $db->prepare("SELECT COALESCE(SUM(quota_units), 0) as total_units FROM recharges WHERE user_id = ? AND status = 'completed'");
        $units_stmt->execute([$user_id]);
    }
    $units_result = $units_stmt->fetch(PDO::FETCH_ASSOC);
    $total_units = (int)($units_result['total_units'] ?? 0);
    $debt_amount = (int)round($total_units * RECHARGE_UNIT_PRICE);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte suspendu - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%); }
        .box { max-width: 650px; margin: 60px auto; padding: 2.5rem; background: #fff; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .title { font-size: 1.8rem; margin-bottom: 0.8rem; color: #e74c3c; }
        .muted { color: #666; margin-bottom: 1.5rem; line-height: 1.6; }
        .debt-info { background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0; }
        .debt-amount { font-size: 1.5rem; font-weight: 700; color: #e74c3c; margin: 0.5rem 0; }
        .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top: 1.5rem; }
        .btn-support { background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); color:#fff; border:none; padding: 0.9rem 1.2rem; border-radius: 12px; text-decoration:none; font-weight:600; display:inline-block; }
        .btn-home { background:#f1f1f1; color:#1a1a1a; border:none; padding: 0.9rem 1.2rem; border-radius: 12px; text-decoration:none; font-weight:600; display:inline-block; }
        .btn-support:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 69, 19, 0.3); }
    </style>
</head>
<body>
<main>
    <div class="container">
        <div class="box">
            <div class="title">⚠️ Compte suspendu</div>
            <?php if ($is_debt_suspended || $reason === 'debt'): ?>
                <div class="muted">
                    Votre compte partenaire a été suspendu automatiquement car vous avez une dette impayée depuis plus de 30 jours.
                </div>
                <div class="debt-info">
                    <div><strong>Montant dû à la plateforme:</strong></div>
                    <div class="debt-amount"><?php echo number_format($debt_amount, 0); ?> $</div>
                    <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        (<?php echo number_format($total_units, 0); ?> unités × <?php echo number_format(RECHARGE_UNIT_PRICE, 2); ?>$)
                    </div>
                    <?php if (!empty($user_info['last_payment_date'])): ?>
                        <div style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                            Dernier paiement: <?php echo date('d/m/Y H:i', strtotime($user_info['last_payment_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="muted" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #e74c3c;">
                    <strong>Action requise:</strong> Vous devez contacter <strong>contact.nestcorp@gmail.com</strong> pour régulariser votre situation et réactiver votre compte.
                </div>
            <?php else: ?>
                <div class="muted">
                    Votre compte partenaire a été suspendu. Veuillez contacter le support NestCorporation pour réactivation.
                </div>
            <?php endif; ?>
            <div class="actions">
                <a class="btn-support" href="mailto:contact.nestcorp@gmail.com?subject=Suspension de compte - Réactivation&body=Bonjour,%0D%0A%0D%0AJe souhaite réactiver mon compte partenaire.%0D%0A%0D%0ACordialement">📧 Contacter le support</a>
                <a class="btn-home" href="<?php echo url('home'); ?>">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
