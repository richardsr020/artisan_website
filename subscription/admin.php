<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

// // Vérifier les privilèges admin
// if (!is_admin()) {
//     header('Location: ' . DASHBOARD_PAGE);
//     exit;
// }

$db = get_db_connection();

// Gérer les actions d'administration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'add_user':
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $email = $_POST['email'];
            $company_name = $_POST['company_name'];
            $subscription_type = $_POST['subscription_type'];
            
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, company_name, subscription_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $company_name, $subscription_type]);
            $message = "Utilisateur ajouté avec succès!";
            break;
            
        case 'update_subscription':
            $user_id = $_POST['user_id'];
            $subscription_end = $_POST['subscription_end'];
            $is_active = $_POST['is_active'] ?? 0;
            
            $stmt = $db->prepare("UPDATE users SET subscription_end = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$subscription_end, $is_active, $user_id]);
            $message = "Abonnement mis à jour!";
            break;
    }
}

// Récupérer tous les utilisateurs
$users_stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques globales
$global_stats_stmt = $db->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_partners,
        COUNT(*) as total_recharges,
        SUM(amount) as total_revenue,
        AVG(amount) as avg_recharge
    FROM recharges 
    WHERE status = 'completed'
");
$global_stats = $global_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #e74c3c 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .admin-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .admin-table {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-active {
            color: #27ae60;
        }
        .status-inactive {
            color: #e74c3c;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo-header">
                <img src="../images/icon.png" alt="Logo Artisan_ND" class="logo">
                <h1>Administration</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="<?php echo subscription_url('dashboard'); ?>">Tableau de bord</a></li>
                    <li><a href="<?php echo subscription_url('admin'); ?>" class="active">Administration</a></li>
                    <li><a href="<?php echo subscription_url('logout'); ?>">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main>
        <div class="container">
            <div class="admin-header">
                <h2>Panneau d'administration</h2>
                <p>Gestion des partenaires et des abonnements</p>
            </div>
            
            <?php if (isset($message)): ?>
                <div class="admin-card" style="background: #d4edda; color: #155724; margin-bottom: 1rem;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-grid">
                <div class="admin-card">
                    <h3>📊 Statistiques globales</h3>
                    <p><strong>Partenaires actifs:</strong> <?php echo $global_stats['total_partners'] ?: '0'; ?></p>
                    <p><strong>Recharges totales:</strong> <?php echo $global_stats['total_recharges'] ?: '0'; ?></p>
                    <p><strong>Revenus totaux:</strong> <?php echo number_format($global_stats['total_revenue'] ?: 0, 2); ?> FCFA</p>
                    <p><strong>Recharge moyenne:</strong> <?php echo number_format($global_stats['avg_recharge'] ?: 0, 2); ?> FCFA</p>
                </div>
                
                <div class="admin-card">
                    <h3>👥 Gestion des partenaires</h3>
                    <button onclick="openAddUserModal()" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        Ajouter un partenaire
                    </button>
                    <button onclick="openSubscriptionModal()" class="btn btn-warning" style="width: 100%;">
                        Gérer les abonnements
                    </button>
                </div>
                
                <div class="admin-card">
                    <h3>⚡ Actions rapides</h3>
                    <a href="#" class="btn" style="width: 100%; margin-bottom: 0.5rem; background: #3498db; color: white;">Générer rapport mensuel</a>
                    <a href="#" class="btn" style="width: 100%; margin-bottom: 0.5rem; background: #27ae60; color: white;">Exporter les données</a>
                    <a href="#" class="btn" style="width: 100%; background: #f39c12; color: white;">Voir les logs système</a>
                </div>
            </div>
            
            <div class="admin-table">
                <h3>Liste des partenaires</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom d'utilisateur</th>
                            <th>Entreprise</th>
                            <th>Email</th>
                            <th>Type d'abonnement</th>
                            <th>Date d'expiration</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['subscription_type']); ?></td>
                                <td><?php echo $user['subscription_end'] ? date('d/m/Y', strtotime($user['subscription_end'])) : 'N/A'; ?></td>
                                <td class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </td>
                                <td>
                                    <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn" style="padding: 5px 10px; font-size: 0.9rem;">Modifier</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Modal Ajout d'utilisateur -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h3>Ajouter un partenaire</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="company_name">Nom de l'entreprise</label>
                    <input type="text" id="company_name" name="company_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="subscription_type">Type d'abonnement</label>
                    <select id="subscription_type" name="subscription_type" class="form-control" required>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 1rem;">
                    <button type="submit" class="btn btn-success">Ajouter</button>
                    <button type="button" onclick="closeModal('addUserModal')" class="btn">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>