<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

$db = get_db_connection();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Vérifier le statut de l'abonnement
$subscription_status = check_subscription_status($user_id);
$subscription_active = $subscription_status['active'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recharge de Quotas - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        }
        .recharge-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .recharge-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .recharge-header h2 {
            color: #1a1a1a;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .recharge-form {
            margin-top: 2rem;
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
        .form-control {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid rgba(204, 204, 204, 0.5);
            border-radius: 12px;
            font-size: 1rem;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
            background: #ffffff;
        }
        .btn-submit {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
            margin-top: 1.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.4);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1) 0%, rgba(255, 127, 80, 0.05) 100%);
            border: 1px solid rgba(255, 140, 0, 0.3);
            color: #1a1a1a;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.1) 0%, rgba(160, 82, 45, 0.05) 100%);
            border: 1px solid rgba(139, 69, 19, 0.3);
            color: #1a1a1a;
        }
        .info-text {
            color: #666666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #8B4513;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #A0522D;
        }
    </style>
</head>
<body>
    
    <main>
        <div class="container">
            <a href="<?php echo subscription_url('dashboard'); ?>" class="back-link">← Retour au tableau de bord</a>
            
            <div class="recharge-container">
                <div class="recharge-header">
                    <h2>Recharger un Quota Client</h2>
                    <p>Remplissez les informations pour générer la clé de recharge cryptographique</p>
                </div>
                
                <?php if (!$subscription_active): ?>
                    <div class="alert alert-error" style="background: linear-gradient(135deg, rgba(255, 140, 0, 0.15) 0%, rgba(255, 127, 80, 0.1) 100%); border: 2px solid #FF8C00;">
                        <strong>⚠️ Abonnement expiré</strong><br>
                        Cher Partenaire, votre abonnement a pris fin. Veuillez renouveler votre abonnement pour pouvoir effectuer des recharges.
                        <br><br>
                        <a href="<?php echo subscription_url('subscription'); ?>" class="btn btn-warning" style="text-decoration: none; display: inline-block; margin-top: 0.5rem;">Renouveler l'abonnement</a>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <strong>Erreur:</strong> <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form id="rechargeForm" class="recharge-form" method="POST" action="../index.php?page=process_recharge" <?php echo !$subscription_active ? 'onsubmit="event.preventDefault(); alert(\'Votre abonnement a expiré. Veuillez le renouveler pour continuer.\'); return false;"' : ''; ?>>
                    
                    <div class="form-group">
                        <label for="client_phone">Numéro de Téléphone *</label>
                        <input type="tel" id="client_phone" name="client_phone" class="form-control" required 
                               placeholder="Ex: +221 77 123 45 67">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_email">Email Client *</label>
                        <input type="email" id="client_email" name="client_email" class="form-control" required 
                               placeholder="Ex: client@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_id">Identifiant Client (ClientID) *</label>
                        <input type="text" id="client_id" name="client_id" class="form-control" required 
                               placeholder="Ex: CLIENT_12345_ABC">
                        <div class="info-text">Identifiant unique du client (machine_id ou client_id)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quota_units">Nombre d'Unités de Quota *</label>
                        <input type="number" id="quota_units" name="quota_units" class="form-control" required 
                               min="1" placeholder="Ex: 100">
                        <div class="info-text">Nombre d'unités (pages) à recharger pour ce client</div>
                        <div class="info-text">Prix unitaire: <?php echo number_format(RECHARGE_UNIT_PRICE, 2); ?>$ / unité</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Montant ($) *</label>
                        <input type="number" id="amount" name="amount" class="form-control" required 
                               min="0" step="0.01" placeholder="Ex: 5000">
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn" <?php echo !$subscription_active ? 'disabled' : ''; ?>>
                        <?php echo $subscription_active ? 'Générer la Clé de Recharge' : 'Abonnement expiré - Renouveler d\'abord'; ?>
                    </button>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        // Gestion de la soumission du formulaire
        document.getElementById('rechargeForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Traitement en cours...';
        });
    </script>
</body>
</html>
