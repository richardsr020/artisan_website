<?php
// Protection contre l'accès direct - doit passer par index.php
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

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
if ($days <= 0 || $days > 365) {
    $days = 30;
}

// KPIs globaux
$kpi_stmt = $db->query("SELECT COUNT(*) as total_partners, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_partners FROM users WHERE subscription_type != 'admin'");
$kpis_partners = $kpi_stmt->fetch(PDO::FETCH_ASSOC);

$kpi_recharges_stmt = $db->prepare("SELECT COUNT(*) as total_recharges, SUM(quota_units) as total_units, SUM(amount) as total_amount FROM recharges WHERE status = 'completed' AND recharge_date >= datetime('now', '-' || ? || ' days')");
$kpi_recharges_stmt->execute([$days]);
$kpis_recharges = $kpi_recharges_stmt->fetch(PDO::FETCH_ASSOC);

$kpis_recharges['total_recharges'] = $kpis_recharges['total_recharges'] ?: 0;
$kpis_recharges['total_units'] = $kpis_recharges['total_units'] ?: 0;
$kpis_recharges['total_amount'] = $kpis_recharges['total_amount'] ?: 0;

$due_amount = $kpis_recharges['total_units'] * RECHARGE_UNIT_PRICE;

// Historique complet des recharges
$partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;

$where = "WHERE r.status = 'completed'";
$params = [];

$where .= " AND r.recharge_date >= datetime('now', '-' || ? || ' days')";
$params[] = $days;

if ($partner_id > 0) {
    $where .= " AND r.user_id = ?";
    $params[] = $partner_id;
}

$history_sql = "
    SELECT r.*, u.username, u.company_name
    FROM recharges r
    LEFT JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.recharge_date DESC
    LIMIT 500
";

$history_stmt = $db->prepare($history_sql);
$history_stmt->execute($params);
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

$partners_stmt = $db->query("SELECT id, username, company_name FROM users WHERE subscription_type != 'admin' ORDER BY created_at DESC");
$partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%); }
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #e74c3c 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem auto;
        }
        .admin-nav { margin-top: 1rem; display: flex; gap: 10px; flex-wrap: wrap; }
        .admin-nav a { text-decoration: none; }
        .admin-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .filters .form-group { margin-bottom: 0; }
    </style>
</head>
<body>
    <main>
        <div class="container">
            <div class="admin-header">
                <h2>Dashboard Administrateur</h2>
                <p>Résumé global + historique complet des recharges</p>
                <div class="admin-nav">
                    <a class="btn" style="background:#ffffff;color:#2c3e50;" href="<?php echo subscription_url('admin_dashboard'); ?>">Dashboard</a>
                    <a class="btn" style="background:#ffffff;color:#2c3e50;" href="<?php echo subscription_url('admin_partners'); ?>">Partenaires</a>
                    <a class="btn" style="background:#ffffff;color:#2c3e50;" href="<?php echo subscription_url('admin'); ?>">Autres (logiciels)</a>
                </div>
            </div>

            <div class="admin-card">
                <form method="GET" action="" class="filters">
                    <input type="hidden" name="page" value="admin_dashboard">
                    <div class="form-group">
                        <label for="days">Période (jours)</label>
                        <input class="form-control" type="number" id="days" name="days" min="1" max="365" value="<?php echo htmlspecialchars((string)$days); ?>">
                    </div>
                    <div class="form-group">
                        <label for="partner_id">Partenaire</label>
                        <select class="form-control" id="partner_id" name="partner_id">
                            <option value="0">Tous</option>
                            <?php foreach ($partners as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo $partner_id === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($p['company_name'] ?: $p['username']) . ' (#' . $p['id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-success" type="submit">Filtrer</button>
                </form>
            </div>

            <div class="admin-card">
                <h3>Résumé global (<?php echo $days; ?> jours)</h3>
                <div class="stats-grid">
                    <div class="admin-card" style="margin:0;">
                        <strong>Partenaires</strong><br>
                        Total: <?php echo (int)($kpis_partners['total_partners'] ?? 0); ?><br>
                        Actifs: <?php echo (int)($kpis_partners['active_partners'] ?? 0); ?>
                    </div>
                    <div class="admin-card" style="margin:0;">
                        <strong>Recharges complétées</strong><br>
                        Ops: <?php echo (int)$kpis_recharges['total_recharges']; ?><br>
                        Unités: <?php echo (int)$kpis_recharges['total_units']; ?>
                    </div>
                    <div class="admin-card" style="margin:0;">
                        <strong>Montant total (saisi)</strong><br>
                        <?php echo number_format((float)$kpis_recharges['total_amount'], 2); ?> $
                    </div>
                    <div class="admin-card" style="margin:0;">
                        <strong>Somme mensuelle à verser (base)</strong><br>
                        <?php echo number_format((float)$due_amount, 2); ?> $<br>
                        <span style="color:#666;">= unités x <?php echo number_format((float)RECHARGE_UNIT_PRICE, 2); ?>$</span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <h3>Historique complet des recharges (max 500)</h3>
                <?php if (empty($history)): ?>
                    <p>Aucune recharge sur la période sélectionnée.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Partenaire</th>
                                <th>Client email</th>
                                <th>Téléphone</th>
                                <th>Unités</th>
                                <th>Montant</th>
                                <th>Transaction</th>
                                <th>Clé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $r): ?>
                                <tr>
                                    <td><?php echo isset($r['recharge_date']) ? date('d/m/Y H:i', strtotime($r['recharge_date'])) : ''; ?></td>
                                    <td>
                                        <?php
                                            $label = ($r['company_name'] ?: $r['username']) ?: ('User #' . $r['user_id']);
                                            echo htmlspecialchars($label);
                                        ?>
                                        <br>
                                        <a href="<?php echo subscription_url('admin_partner', ['id' => (int)$r['user_id']]); ?>" style="font-weight:600; text-decoration:none; color:#8B4513;">Voir</a>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['client_email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($r['client_phone'] ?? ''); ?></td>
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
        </div>
    </main>
</body>
</html>
