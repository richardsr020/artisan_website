<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

// Inclure la configuration du système d'abonnement
require_once __DIR__ . '/config.php';

// Vérifier que la fonction est disponible
if (!function_exists('redirect_if_logged_in')) {
    error_log("Erreur: redirect_if_logged_in() n'est pas définie dans subscription/config.php");
    die("Erreur de configuration. Veuillez contacter l'administrateur.");
}

// Rediriger si déjà connecté
redirect_if_logged_in();

$error = '';

// Vérifier si l'utilisateur a été redirigé depuis la page de téléchargement
if (isset($_GET['error']) && $_GET['error'] === 'login_required' && isset($_GET['redirect']) && $_GET['redirect'] === 'download') {
    $error = "Vous devez vous connecter ou créer un compte pour télécharger Artisan_ND.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation basique
    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        // Vérifier les tentatives de connexion
        if (!check_login_attempts($username)) {
            $error = "Trop de tentatives de connexion échouées. Veuillez réessayer dans " . (LOGIN_LOCKOUT_TIME / 60) . " minutes.";
        } else {
            $db = get_db_connection();
            
            // Rechercher l'utilisateur
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = ($user['subscription_type'] === 'admin');
                $_SESSION['company_name'] = $user['company_name'];
                $_SESSION['email'] = $user['email'];
                
                // Régénérer l'ID de session pour la sécurité
                session_regenerate_id(true);
                
                // Journaliser la connexion réussie
                log_activity($user['id'], 'login', "Connexion réussie");

                if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                    header('Location: ' . subscription_url('suspended'));
                    exit;
                }
                
                // Rediriger vers le dashboard ou l'URL sauvegardée
                $redirect_url = isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login']) 
                    ? $_SESSION['redirect_after_login'] 
                    : DASHBOARD_PAGE;
                unset($_SESSION['redirect_after_login']);
                
                header('Location: ' . $redirect_url);
                exit;
            } else {
                // Connexion échouée
                $error = "Identifiants incorrects";
                
                // Journaliser la tentative échouée - transaction courte
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $db->beginTransaction();
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([null, 'login_failed', "Tentative de connexion échouée pour: $username", $ip_address]);
                $db->commit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Partenaire - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        }
        .login-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .login-header h2 {
            color: #1a1a1a;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #666666;
        }
        .error {
            color: #1a1a1a;
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1) 0%, rgba(255, 127, 80, 0.05) 100%);
            border: 1px solid rgba(255, 140, 0, 0.3);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid rgba(204, 204, 204, 0.5);
            border-radius: 12px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            background: #ffffff;
        }
        .btn-primary {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: #ffffff;
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
    </style>
</head>
<body>

    <main>
        <div class="container">
            <div class="login-container">
                <div class="login-header">
                    <h2>Connexion Partenaire</h2>
                    <p>Accédez à votre tableau de bord</p>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Se connecter</button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <p>Pas encore de compte ? <a href="<?php echo REGISTER_PAGE; ?>">S'inscrire</a></p>
                </div>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="<?php echo url('home'); ?>">← Retour au site principal</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>