<?php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

if (!is_admin()) {
    header('Location: ' . DASHBOARD_PAGE);
    exit;
}

$db = get_db_connection();

$partner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($partner_id <= 0) {
    header('Location: ' . subscription_url('admin_partners'));
    exit;
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'deactivate_partner') {
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND subscription_type != 'admin'");
        $stmt->execute([$partner_id]);
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'] ?? null, 'admin_deactivate_partner', 'Partner id=' . $partner_id);
        }
        $message = 'Partenaire désactivé.';
    } elseif ($action === 'activate_partner') {
        $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND subscription_type != 'admin'");
        $stmt->execute([$partner_id]);
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'] ?? null, 'admin_activate_partner', 'Partner id=' . $partner_id);
        }
        $message = 'Partenaire activé.';
    }
}

$partner_stmt = $db->prepare("SELECT id, username, email, company_name, phone, is_active, created_at FROM users WHERE id = ? AND subscription_type != 'admin'");
$partner_stmt->execute([$partner_id]);
$partner = $partner_stmt->fetch(PDO::FETCH_ASSOC);
if (!$partner) {
    header('Location: ' . subscription_url('admin_partners'));
    exit;
}

$partner_label = ($partner['company_name'] ?: $partner['username']);

$stats_30_stmt = $db->prepare("SELECT COUNT(*) as ops, COALESCE(SUM(quota_units),0) as units, COALESCE(SUM(amount),0) as amount FROM recharges WHERE user_id = ? AND status='completed' AND recharge_date >= datetime('now','-30 days')");
$stats_30_stmt->execute([$partner_id]);
$stats_30 = $stats_30_stmt->fetch(PDO::FETCH_ASSOC);
$ops_30 = (int)($stats_30['ops'] ?? 0);
$units_30 = (int)($stats_30['units'] ?? 0);
$due_30 = $units_30 * RECHARGE_UNIT_PRICE;

$stats_month_stmt = $db->prepare("SELECT COUNT(*) as ops, COALESCE(SUM(quota_units),0) as units, COALESCE(SUM(amount),0) as amount FROM recharges WHERE user_id = ? AND status='completed' AND strftime('%Y-%m', recharge_date) = strftime('%Y-%m', 'now')");
$stats_month_stmt->execute([$partner_id]);
$stats_month = $stats_month_stmt->fetch(PDO::FETCH_ASSOC);
$ops_month = (int)($stats_month['ops'] ?? 0);
$units_month = (int)($stats_month['units'] ?? 0);
$due_month = $units_month * RECHARGE_UNIT_PRICE;

$recharges_stmt = $db->prepare("SELECT id, client_email, client_phone, quota_units, amount, transaction_id, status, recharge_date, encrypted_file_path FROM recharges WHERE user_id = ? ORDER BY recharge_date DESC LIMIT 200");
$recharges_stmt->execute([$partner_id]);
$recharges = $recharges_stmt->fetchAll(PDO::FETCH_ASSOC);

$logs_stmt = $db->prepare("SELECT action, details, ip_address, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 200");
$logs_stmt->execute([$partner_id]);
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Partenaire</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: #fafafa; }
        .card { background:#fff; padding:1.2rem; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.05); margin: 1rem 0; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px; border-bottom:1px solid #eee; text-align:left; }
        th { background:#f8f9fa; }
        .status-active { color:#27ae60; font-weight:700; }
        .status-inactive { color:#e74c3c; font-weight:700; }
        .topbar { background: linear-gradient(135deg, #2c3e50 0%, #e74c3c 100%); color:#fff; padding:1.5rem; border-radius:10px; margin-top:1.5rem; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:0.8rem; }
    </style>
</head>
<body>
<main>
    <div class="container">
        <div class="topbar">
            <h2>Fiche Partenaire</h2>
            <div><?php echo htmlspecialchars($partner_label); ?> (#<?php echo (int)$partner_id; ?>)</div>
            <div class="actions">
                <a class="btn" style="background:#fff;color:#2c3e50;" href="<?php echo subscription_url('admin_dashboard'); ?>">Dashboard</a>
                <a class="btn" style="background:#fff;color:#2c3e50;" href="<?php echo subscription_url('admin_partners'); ?>">Partenaires</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="card" style="background:#d4edda; color:#155724;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Infos</h3>
            <div><strong>Email:</strong> <?php echo htmlspecialchars($partner['email'] ?? ''); ?></div>
            <div><strong>Téléphone:</strong> <?php echo htmlspecialchars($partner['phone'] ?? ''); ?></div>
            <div><strong>Créé le:</strong> <?php echo !empty($partner['created_at']) ? date('d/m/Y H:i', strtotime($partner['created_at'])) : ''; ?></div>
            <div><strong>Statut:</strong>
                <span class="status-<?php echo ((int)$partner['is_active'] === 1) ? 'active' : 'inactive'; ?>">
                    <?php echo ((int)$partner['is_active'] === 1) ? 'Actif' : 'Inactif'; ?>
                </span>
            </div>
            <div class="actions">
                <?php if ((int)$partner['is_active'] === 1): ?>
                    <form method="POST" action="<?php echo subscription_url('admin_partner', ['id' => $partner_id]); ?>">
                        <input type="hidden" name="action" value="deactivate_partner">
                        <button class="btn" style="background:#e74c3c; color:#fff;" type="submit">Désactiver</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?php echo subscription_url('admin_partner', ['id' => $partner_id]); ?>">
                        <input type="hidden" name="action" value="activate_partner">
                        <button class="btn" style="background:#27ae60; color:#fff;" type="submit">Activer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Résumé (calcul base: unités x <?php echo number_format((float)RECHARGE_UNIT_PRICE, 2); ?>$)</h3>
            <div><strong>30 jours:</strong> <?php echo $ops_30; ?> ops, <?php echo $units_30; ?> unités, dû <?php echo number_format((float)$due_30, 2); ?> $</div>
            <div><strong>Mois courant:</strong> <?php echo $ops_month; ?> ops, <?php echo $units_month; ?> unités, dû <?php echo number_format((float)$due_month, 2); ?> $</div>
        </div>

        <div class="card">
            <h3>Recharges (max 200)</h3>
            <?php if (empty($recharges)): ?>
                <p>Aucune recharge.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Unités</th>
                            <th>Montant</th>
                            <th>Transaction</th>
                            <th>Clé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recharges as $r): ?>
                            <tr>
                                <td><?php echo !empty($r['recharge_date']) ? date('d/m/Y H:i', strtotime($r['recharge_date'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($r['client_email'] ?? ''); ?><br><?php echo htmlspecialchars($r['client_phone'] ?? ''); ?></td>
                                <td><?php echo (int)($r['quota_units'] ?? 0); ?></td>
                                <td><?php echo number_format((float)($r['amount'] ?? 0), 2); ?> $</td>
                                <td><?php echo htmlspecialchars($r['transaction_id'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($r['encrypted_file_path'])): ?>
                                        <a href="<?php echo subscription_url('download_key', ['id' => (int)$r['id']]); ?>" style="font-weight:600; text-decoration:none; color:#8B4513;">Télécharger</a>
                                    <?php else: ?>
                                        <span style="color:#999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Activity logs (max 200)</h3>
            <?php if (empty($logs)): ?>
                <p>Aucun log.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td><?php echo !empty($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($l['action'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($l['details'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($l['ip_address'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
