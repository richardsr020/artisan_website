<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

// Inclure config.php principal pour avoir accès à url()
if (!function_exists('url')) {
    require_once __DIR__ . '/../config.php';
}

$db = get_db_connection();

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$recharge_stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as revenue FROM recharges WHERE user_id = ? AND status = 'completed'");
$recharge_stmt->execute([$user_id]);
$stats = $recharge_stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les recharges récentes
$recent_stmt = $db->prepare("SELECT * FROM recharges WHERE user_id = ? ORDER BY recharge_date DESC LIMIT 10");
$recent_stmt->execute([$user_id]);
$recent_recharges = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        }
        .dashboard-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            color: #1a1a1a;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(139, 69, 19, 0.15);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #8B4513 0%, #FF8C00 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .recharge-form {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        .recent-recharges {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid rgba(204, 204, 204, 0.3);
        }
        th {
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.05) 0%, rgba(255, 140, 0, 0.05) 100%);
            font-weight: 600;
            color: #1a1a1a;
        }
        .status-completed {
            color: #8B4513;
            font-weight: 600;
        }
        .status-pending {
            color: #FF8C00;
            font-weight: 600;
        }
        .btn-success {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
        .form-control {
            border-radius: 12px;
            border: 1px solid rgba(204, 204, 204, 0.5);
            padding: 0.9rem 1.2rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(160, 82, 45, 0.05) 100%);
            border: 1px solid rgba(139, 69, 19, 0.3);
            color: #1a1a1a;
        }
        .alert-warning {
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.15) 0%, rgba(255, 127, 80, 0.1) 100%);
            border: 2px solid #FF8C00;
            color: #1a1a1a;
            padding: 1.5rem;
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(160, 82, 45, 0.05) 100%);
            border: 1px solid rgba(139, 69, 19, 0.3);
            color: #1a1a1a;
        }
        .btn-download {
            color: #8B4513;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .btn-download:hover {
            color: #A0522D;
        }
    </style>
</head>
<body>

    <main>
        <div class="container">
            <?php 
            // Vérifier le statut de l'abonnement
            $subscription_status = check_subscription_status($user_id);
            
            if (!$subscription_status['active']): 
            ?>
                <div class="alert alert-warning" style="margin-bottom: 2rem; background: linear-gradient(135deg, rgba(255, 140, 0, 0.15) 0%, rgba(255, 127, 80, 0.1) 100%); border: 2px solid #FF8C00;">
                    <strong>⚠️ Attention!</strong> Cher Partenaire, votre abonnement a pris fin. Veuillez renouveler votre abonnement pour continuer à utiliser nos services.
                    <br><br>
                        <a href="<?php echo subscription_url('subscription'); ?>" class="btn btn-warning" style="text-decoration: none; display: inline-block; margin-top: 0.5rem;">Renouveler l'abonnement</a>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && $_GET['success'] == 'recharge_completed'): ?>
                <div class="alert alert-success" style="margin-bottom: 2rem;">
                    <strong>✓ Succès!</strong> La recharge a été effectuée avec succès. Le fichier de clé cryptée a été généré.
                </div>
            <?php endif; ?>
            
            <div class="dashboard-header">
                <h2>Bienvenue, <?php echo htmlspecialchars($user['company_name'] ?: $user['username']); ?>!</h2>
                <p>
                <?php 
                if ($subscription_status['active'] && $subscription_status['days_remaining'] !== null): 
                    $days = $subscription_status['days_remaining'];
                    if ($days == 0) {
                        echo '<span style="color: #FF8C00; font-weight: 600;">Réabonnement aujourd\'hui</span>';
                    } elseif ($days == 1) {
                        echo '<span style="color: #FF8C00; font-weight: 600;">Réabonnement dans 1 jour</span>';
                    } else {
                        echo '<span style="color: #8B4513; font-weight: 600;">Réabonnement dans ' . $days . ' jours</span>';
                    }
                elseif (!$subscription_status['active']): 
                    echo '<span style="color: #FF8C00; font-weight: 600;">Abonnement expiré - Renouvellement requis</span>';
                else:
                    echo '<span style="color: #666; font-weight: 600;">Abonnement non configuré</span>';
                endif; 
                ?>
                </p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Recharges totales</div>
                    <div class="stat-value"><?php echo $stats['total'] ?: '0'; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Revenus totaux</div>
                    <div class="stat-value"><?php echo number_format($stats['revenue'] ?: 0, 2); ?>$</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Réabonnement dans: </div>
                    <div class="stat-value">
                    <?php 
                    if ($subscription_status['active'] && $subscription_status['days_remaining'] !== null): 
                        $days = $subscription_status['days_remaining'];
                        if ($days == 0) {
                            echo '<span style="color: #FF8C00;">Aujourd\'hui</span>';
                        } elseif ($days == 1) {
                            echo '<span style="color: #FF8C00;">1 jour</span>';
                        } else {
                            echo '<span style="color: #8B4513;">' . $days . ' jours</span>';
                        }
                    elseif (!$subscription_status['active']): 
                        echo '<span style="color: #FF8C00; font-size: 1.2rem;">Expiré</span>';
                    else:
                        echo '<span style="color: #999;">-</span>';
                    endif; 
                    ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Statut</div>
                    <div class="stat-value"><?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?></div>
                </div>
            </div>
            
            <div class="recharge-form">
                <h3>Effectuer une recharge</h3>
                <p style="margin-bottom: 1.5rem; color: #666666;">Utilisez le formulaire complet pour générer une clé de recharge cryptographique</p>
                <a href="<?php echo subscription_url('recharge'); ?>" class="btn btn-success" style="text-decoration: none; display: inline-block; text-align: center;">Accéder au formulaire de recharge</a>
            </div>
            
            <div class="recent-recharges">
                <h3>Recharges récentes</h3>
                <?php if (empty($recent_recharges)): ?>
                    <p>Aucune recharge effectuée pour le moment.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Email client</th>
                                <th>Téléphone</th>
                                <th>Unités</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_recharges as $recharge): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($recharge['recharge_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($recharge['client_email']); ?></td>
                                    <td><?php echo htmlspecialchars($recharge['client_phone']); ?></td>
                                    <td><?php echo $recharge['quota_units']; ?></td>
                                    <td><?php echo number_format($recharge['amount'], 2); ?> $</td>
                                    <td class="status-<?php echo $recharge['status']; ?>">
                                        <?php echo $recharge['status'] == 'completed' ? 'Complétée' : 'En attente'; ?>
                                    </td>
                                    <td>
                                        <?php if ($recharge['status'] == 'completed' && !empty($recharge['encrypted_file_path'])): ?>
                                            <a href="<?php echo subscription_url('download_key', ['id' => $recharge['id']]); ?>" class="btn-download" style="color: #8B4513; text-decoration: none; font-weight: 600;">📥 Télécharger</a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
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
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Artisan_ND - Système de recharge des quotas</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>