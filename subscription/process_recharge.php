<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

$db = get_db_connection();
$user_id = $_SESSION['user_id'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier d'abord le statut de l'abonnement
    $subscription_status = check_subscription_status($user_id);
    
    if (!$subscription_status['active']) {
        $error_message = "Votre abonnement a expiré. Veuillez renouveler votre abonnement pour effectuer des recharges.";
        header('Location: ' . RECHARGE_PAGE . '&error=' . urlencode($error_message));
        exit;
    }
    
    // Récupération et validation des données
    $client_phone = sanitize_input($_POST['client_phone'] ?? '');
    $client_email = sanitize_input($_POST['client_email'] ?? '');
    $quota_units = intval($_POST['quota_units'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validation
    if (empty($client_phone)) {
        $errors[] = "Le numéro de téléphone est requis";
    }
    
    if (empty($client_email) || !validate_email($client_email)) {
        $errors[] = "Un email valide est requis";
    }
    
    if ($quota_units <= 0) {
        $errors[] = "Le nombre d'unités doit être supérieur à 0";
    }
    
    if ($amount <= 0) {
        $errors[] = "Le montant doit être supérieur à 0";
    }
    
    // Vérifier le fichier de clé publique
    if (!isset($_FILES['public_key']) || $_FILES['public_key']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Le fichier de clé publique est requis";
    } else {
        $public_key_file = $_FILES['public_key'];
        
        // Vérifier le type de fichier
        $allowed_types = ['text/plain', 'text/txt'];
        $file_extension = strtolower(pathinfo($public_key_file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== 'txt') {
            $errors[] = "Le fichier doit être un fichier .txt";
        }
        
        // Lire le contenu de la clé publique
        $public_key_pem = file_get_contents($public_key_file['tmp_name']);
        
        if (empty($public_key_pem)) {
            $errors[] = "Le fichier de clé publique est vide";
        }
        
        // Vérifier que c'est bien une clé PEM
        if (strpos($public_key_pem, '-----BEGIN PUBLIC KEY-----') === false) {
            $errors[] = "Le fichier ne contient pas une clé publique PEM valide";
        }
    }
    
    // Si pas d'erreurs, procéder au traitement
    if (empty($errors)) {
        try {
            // Préparer les données pour Python
            $python_data = [
                'public_key_pem' => $public_key_pem,
                'phone_number' => $client_phone,
                'email' => $client_email,
                'limit' => $quota_units
            ];
            
            // Encoder en JSON et échapper pour la ligne de commande
            $json_data = json_encode($python_data);
            $escaped_json = escapeshellarg($json_data);
            
            // Chemin vers le script Python
            $python_script = __DIR__ . '/artisan_sv/server.py';
            
            // Exécuter le script Python
            $command = "python3 " . escapeshellarg($python_script) . " " . $escaped_json . " 2>&1";
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception("Erreur lors de l'exécution du script Python");
            }
            
            // Décoder la réponse JSON
            $result = json_decode(trim($output), true);
            
            if (!$result || !isset($result['success'])) {
                throw new Exception("Réponse invalide du script Python: " . $output);
            }
            
            if (!$result['success']) {
                throw new Exception($result['error'] ?? "Erreur inconnue lors du cryptage");
            }
            
            // Récupérer le message crypté
            $encrypted_message_base64 = $result['encrypted_message'];
            $encrypted_message = base64_decode($encrypted_message_base64);
            
            // Créer le dossier pour les fichiers cryptés s'il n'existe pas
            $encrypted_dir = __DIR__ . '/encrypted_keys';
            if (!is_dir($encrypted_dir)) {
                mkdir($encrypted_dir, 0755, true);
            }
            
            // Générer un nom de fichier unique
            $transaction_id = uniqid('RCH_' . date('Ymd_') . $user_id . '_', true);
            $encrypted_file_name = $client_email . '_' . $transaction_id . '.bin';
            $encrypted_file_path = $encrypted_dir . '/' . $encrypted_file_name;
            
            // Sauvegarder le fichier crypté
            file_put_contents($encrypted_file_path, $encrypted_message);
            
            // Stocker dans la base de données avec les champs du formulaire
            $stmt = $db->prepare("
                INSERT INTO recharges (
                    user_id, client_phone, client_email, quota_units, amount, 
                    encrypted_file_path, transaction_id, status, completed_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', datetime('now'))
            ");
            
            $stmt->execute([
                $user_id,
                $client_phone,
                $client_email,
                $quota_units,
                $amount,
                $encrypted_file_path,
                $transaction_id
            ]);
            
            $recharge_id = $db->lastInsertId();
            
            // Journaliser l'action
            log_activity($user_id, 'recharge_completed', "Recharge effectuée pour client: $client_email (ID: $recharge_id)");
            
            $success = true;
            
            // Rediriger vers le dashboard avec un message de succès
            header('Location: ' . DASHBOARD_PAGE . '&success=recharge_completed&id=' . $recharge_id);
            exit;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la recharge: " . $e->getMessage());
            $errors[] = "Erreur lors du traitement: " . $e->getMessage();
        }
    }
}

// Si on arrive ici, il y a eu des erreurs
// Rediriger vers la page de recharge avec les erreurs
$error_message = implode(', ', $errors);
header('Location: ' . RECHARGE_PAGE . '&error=' . urlencode($error_message));
exit;
?>
