<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

// Vérifier les privilèges admin
if (!is_admin()) {
    header('Location: ' . DASHBOARD_PAGE);
    exit;
}

$legacy = isset($_GET['legacy']) && ($_GET['legacy'] === '1' || $_GET['legacy'] === 'true');
if (!$legacy) {
    header('Location: ' . subscription_url('admin_dashboard'));
    exit;
}

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

        case 'add_software_link':
            $name = sanitize_input($_POST['software_name'] ?? '');
            $version = sanitize_input($_POST['software_version'] ?? '');
            $description = sanitize_input($_POST['software_description'] ?? '');
            $download_url = trim($_POST['software_download_url'] ?? '');

            if (empty($name)) {
                $message = "Le nom du logiciel est requis.";
                break;
            }

            if (empty($download_url) || !filter_var($download_url, FILTER_VALIDATE_URL)) {
                $message = "Veuillez fournir une URL valide (ex: lien GitHub Release).";
                break;
            }

            $uploaded_by = $_SESSION['user_id'] ?? null;
            $file_name = basename(parse_url($download_url, PHP_URL_PATH) ?? '');
            if (empty($file_name)) {
                $file_name = 'download';
            }

            $stmt = $db->prepare("INSERT INTO software (name, version, description, file_name, file_path, download_url, file_size, uploaded_by_user_id, is_active) VALUES (?, ?, ?, ?, '', ?, NULL, ?, 1)");
            $stmt->execute([$name, $version, $description, $file_name, $download_url, $uploaded_by]);

            if (function_exists('log_activity')) {
                log_activity($uploaded_by, 'add_software_link', 'Ajout lien logiciel: ' . $download_url);
            }

            $message = "Lien GitHub enregistré avec succès!";
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

// Récupérer les logiciels uploadés
$software_stmt = $db->query("SELECT * FROM software ORDER BY created_at DESC");
$software_list = $software_stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            <div class="admin-table" style="margin-bottom: 2rem;">
                <h3>Gestion des versions (GitHub)</h3>
                <form method="POST" action="" style="margin-bottom: 1.5rem;">
                    <input type="hidden" name="action" value="add_software_link">

                    <div class="form-group">
                        <label for="software_name">Nom</label>
                        <input type="text" id="software_name" name="software_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="software_version">Version</label>
                        <input type="text" id="software_version" name="software_version" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="software_description">Description</label>
                        <input type="text" id="software_description" name="software_description" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="software_download_url">Lien de téléchargement (GitHub)</label>
                        <input type="url" id="software_download_url" name="software_download_url" class="form-control" placeholder="https://github.com/<org>/<repo>/releases/download/v1.0.0/Artisan_ND_1.0.0.zip" required>
                    </div>

                    <button type="submit" class="btn btn-success">Enregistrer le lien</button>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Version</th>
                            <th>Fichier</th>
                            <th>Taille</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($software_list as $sw): ?>
                            <tr>
                                <td><?php echo $sw['id']; ?></td>
                                <td><?php echo htmlspecialchars($sw['name']); ?></td>
                                <td><?php echo htmlspecialchars($sw['version'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($sw['file_name']); ?></td>
                                <td><?php echo number_format(($sw['file_size'] ?? 0) / 1024 / 1024, 2); ?> MB</td>
                                <td><?php echo isset($sw['created_at']) ? date('d/m/Y H:i', strtotime($sw['created_at'])) : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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