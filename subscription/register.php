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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $company_name = sanitize_input($_POST['company_name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    
    // Validation
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis";
    } elseif (strlen($username) < 3) {
        $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et underscores";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est requis";
    } elseif (!validate_email($email)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif (!validate_password($password)) {
        $errors[] = "Le mot de passe doit contenir au moins " . MIN_PASSWORD_LENGTH . " caractères, dont au moins une lettre et un chiffre";
    }
    
    if ($password !== $password_confirm) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    if (empty($company_name)) {
        $errors[] = "Le nom de l'entreprise est requis";
    }
    
    // Si pas d'erreurs, procéder à l'inscription
    if (empty($errors)) {
        $db = get_db_connection();
        
        try {
            // Vérifier si le username existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = "Ce nom d'utilisateur est déjà utilisé";
            }
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
            }
            
            // Si toujours pas d'erreurs, créer le compte
            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $subscription_start = date('Y-m-d');
                $subscription_end = date('Y-m-d', strtotime('+1 month'));
                
                $stmt = $db->prepare("
                    INSERT INTO users (username, password_hash, email, company_name, phone, subscription_type, subscription_start, subscription_end, is_active) 
                    VALUES (?, ?, ?, ?, ?, 'premium', ?, ?, 1)
                ");
                
                $stmt->execute([
                    $username,
                    $password_hash,
                    $email,
                    $company_name,
                    $phone,
                    $subscription_start,
                    $subscription_end
                ]);
                
                $user_id = $db->lastInsertId();
                
                // Journaliser l'inscription
                log_activity($user_id, 'register', "Inscription de l'utilisateur: $username");
                
                // Créer un paiement initial (abonnement)
                $payment_stmt = $db->prepare("
                    INSERT INTO payments (user_id, amount, payment_method, status) 
                    VALUES (?, 25000, 'subscription', 'completed')
                ");
                $payment_stmt->execute([$user_id]);
                
                $success = true;
                
                // Optionnel: connecter automatiquement l'utilisateur
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = false;
                $_SESSION['company_name'] = $company_name;
                
                // Rediriger vers le dashboard après 2 secondes
                header("Refresh: 2; url=" . DASHBOARD_PAGE);
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de l'inscription: " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Partenaire - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        }
        .register-container {
            max-width: 550px;
            margin: 50px auto;
            padding: 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .register-header h2 {
            color: #1a1a1a;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 700;
        }
        .register-header p {
            color: #666666;
        }
        .error {
            color: #1a1a1a;
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1) 0%, rgba(255, 127, 80, 0.05) 100%);
            border: 1px solid rgba(255, 140, 0, 0.3);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            list-style: none;
        }
        .error li {
            margin: 5px 0;
        }
        .success {
            color: #1a1a1a;
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(160, 82, 45, 0.05) 100%);
            border: 1px solid rgba(139, 69, 19, 0.3);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        .form-group input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid rgba(204, 204, 204, 0.5);
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            background: #ffffff;
        }
        .password-hint {
            font-size: 0.85rem;
            color: #666666;
            margin-top: 0.25rem;
        }
        .btn {
            width: 100%;
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666666;
        }
        .login-link a {
            color: #8B4513;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .login-link a:hover {
            color: #A0522D;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <main>
        <div class="container">
            <div class="register-container">
                <div class="register-header">
                    <h2>Créer un compte partenaire</h2>
                    <p>Rejoignez notre réseau de partenaires revendeurs</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="success">
                        ✓ Inscription réussie ! Redirection vers votre tableau de bord...
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <ul class="error">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Nom d'utilisateur *</label>
                            <input type="text" id="username" name="username" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   pattern="[a-zA-Z0-9_]+" minlength="3">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Mot de passe *</label>
                            <input type="password" id="password" name="password" required minlength="<?php echo MIN_PASSWORD_LENGTH; ?>">
                            <div class="password-hint">
                                Minimum <?php echo MIN_PASSWORD_LENGTH; ?> caractères, avec au moins une lettre et un chiffre
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Confirmer le mot de passe *</label>
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="<?php echo MIN_PASSWORD_LENGTH; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company_name">Nom de l'entreprise *</label>
                            <input type="text" id="company_name" name="company_name" required 
                                   value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   placeholder="Ex: +221 77 123 45 67">
                        </div>
                        
                        <button type="submit" class="btn">S'inscrire</button>
                    </form>
                    
                    <div class="login-link">
                        <p>Déjà inscrit ? <a href="<?php echo LOGIN_PAGE; ?>">Se connecter</a></p>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?php echo url('home'); ?>">← Retour au site principal</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

