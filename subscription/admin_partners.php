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

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $partner_id = isset($_POST['partner_id']) ? intval($_POST['partner_id']) : 0;

    if ($partner_id > 0) {
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
}

$search = trim($_GET['q'] ?? '');

$sql = "SELECT id, username, email, company_name, phone, subscription_type, is_active, created_at FROM users WHERE subscription_type != 'admin'";
$params = [];
if ($search !== '') {
    $sql .= " AND (username LIKE ? OR email LIKE ? OR company_name LIKE ? OR phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_stmt = $db->prepare("SELECT user_id, COUNT(*) as ops, SUM(quota_units) as units, SUM(amount) as amount FROM recharges WHERE status='completed' AND recharge_date >= datetime('now','-30 days') GROUP BY user_id");
$stats_stmt->execute();
$stats_rows = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
$stats_map = [];
foreach ($stats_rows as $row) {
    $stats_map[(int)$row['user_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Partenaires</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%); }
        .admin-header { background: linear-gradient(135deg, #2c3e50 0%, #e74c3c 100%); color: #fff; padding: 2rem; border-radius: 10px; margin: 2rem auto; }
        .admin-card { background: #fff; padding: 1.5rem; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .status-active { color: #27ae60; font-weight: 700; }
        .status-inactive { color: #e74c3c; font-weight: 700; }
        .row-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .row-actions form { margin: 0; }
        .pill { display:inline-block; padding: 4px 10px; border-radius: 999px; background:#f1f1f1; }
    </style>
</head>
<body>
<main>
    <div class="container">
        <div class="admin-header">
            <h2>Gestion des Partenaires</h2>
            <p>Sanctions (activer/désactiver) + accès aux fiches détaillées</p>
            <div style="margin-top:1rem; display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn" style="background:#fff;color:#2c3e50;" href="<?php echo subscription_url('admin_dashboard'); ?>">Dashboard</a>
                <a class="btn" style="background:#fff;color:#2c3e50;" href="<?php echo subscription_url('admin_partners'); ?>">Partenaires</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="admin-card" style="background:#d4edda; color:#155724;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <form method="GET" action="">
                <input type="hidden" name="page" value="admin_partners">
                <div class="form-group">
                    <label for="q">Recherche</label>
                    <input class="form-control" id="q" name="q" type="text" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, email, entreprise, téléphone">
                </div>
                <button class="btn btn-success" type="submit">Rechercher</button>
            </form>
        </div>

        <div class="admin-card">
            <h3>Liste des partenaires</h3>
            <p class="pill">Calcul 30 jours: unités x <?php echo number_format((float)RECHARGE_UNIT_PRICE, 2); ?>$</p>
            <?php if (empty($partners)): ?>
                <p>Aucun partenaire.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Partenaire</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>30 jours</th>
                            <th>Somme à verser (30j)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($partners as $p): ?>
                        <?php
                            $pid = (int)$p['id'];
                            $label = ($p['company_name'] ?: $p['username']);
                            $s = $stats_map[$pid] ?? ['ops' => 0, 'units' => 0, 'amount' => 0];
                            $units = (int)($s['units'] ?? 0);
                            $ops = (int)($s['ops'] ?? 0);
                            $due = $units * RECHARGE_UNIT_PRICE;
                        ?>
                        <tr>
                            <td><?php echo $pid; ?></td>
                            <td>
                                <?php echo htmlspecialchars($label); ?><br>
                                <a href="<?php echo subscription_url('admin_partner', ['id' => $pid]); ?>" style="font-weight:600; text-decoration:none; color:#8B4513;">Voir fiche</a>
                            </td>
                            <td><?php echo htmlspecialchars($p['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($p['phone'] ?? ''); ?></td>
                            <td class="status-<?php echo ((int)$p['is_active'] === 1) ? 'active' : 'inactive'; ?>">
                                <?php echo ((int)$p['is_active'] === 1) ? 'Actif' : 'Inactif'; ?>
                            </td>
                            <td><?php echo $ops; ?> ops / <?php echo $units; ?> unités</td>
                            <td><?php echo number_format((float)$due, 2); ?> $</td>
                            <td>
                                <div class="row-actions">
                                    <?php if ((int)$p['is_active'] === 1): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="deactivate_partner">
                                            <input type="hidden" name="partner_id" value="<?php echo $pid; ?>">
                                            <button class="btn" style="background:#e74c3c; color:#fff;" type="submit">Désactiver</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="activate_partner">
                                            <input type="hidden" name="partner_id" value="<?php echo $pid; ?>">
                                            <button class="btn" style="background:#27ae60; color:#fff;" type="submit">Activer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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
