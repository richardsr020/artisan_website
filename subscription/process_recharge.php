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
    // IMPORTANT: ne pas altérer les données qui entrent dans le chiffrement (pas de htmlspecialchars ici)
    $client_phone = trim((string)($_POST['client_phone'] ?? ''));
    $client_email = trim((string)($_POST['client_email'] ?? ''));
    $client_id = trim((string)($_POST['client_id'] ?? ''));
    $quota_units = intval($_POST['quota_units'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validation
    if (empty($client_phone)) {
        $errors[] = "Le numéro de téléphone est requis";
    }
    
    if (empty($client_email) || !validate_email($client_email)) {
        $errors[] = "Un email valide est requis";
    }
    
    if (empty($client_id)) {
        $errors[] = "L'identifiant client (ClientID) est requis";
    }
    
    if ($quota_units <= 0) {
        $errors[] = "Le nombre d'unités doit être supérieur à 0";
    }
    
    if ($amount <= 0) {
        $errors[] = "Le montant doit être supérieur à 0";
    }
    
    // Si pas d'erreurs, procéder au traitement
    if (empty($errors)) {
        try {
            // Préférer l'interpréteur Python du virtualenv 'Env' s'il existe
            $venv_python = __DIR__ . '/Env/bin/python';
            if (!is_executable($venv_python)) {
                // fallback: tenter python3 système
                $venv_python = trim(shell_exec('which python3 2>/dev/null')) ?: 'python3';
            }
            
            // Chemin vers le script generate_license.py
            $python_script = __DIR__ . '/artisanSV/generate_license.py';
            
            if (!file_exists($python_script)) {
                throw new Exception("Le script generate_license.py est introuvable: " . $python_script);
            }
            
            // Créer le dossier pour stocker les licences générées (.txt format)
            $licenses_dir = __DIR__ . '/artisanSV/lic';
            if (!is_dir($licenses_dir)) {
                mkdir($licenses_dir, 0755, true);
            }
            
            // Générer un identifiant de transaction unique
            $transaction_id = uniqid('RCH_' . date('Ymd_') . $user_id . '_', true);
            $safe_email_for_filename = preg_replace('/[^A-Za-z0-9._@-]+/', '_', $client_email);
            if ($safe_email_for_filename === null || $safe_email_for_filename === '') {
                $safe_email_for_filename = 'client';
            }
            
            // Nom du fichier de licence de sortie (.txt format pour le client)
            $license_filename = $safe_email_for_filename . '_' . $transaction_id . '.txt';
            $license_file_path = $licenses_dir . '/' . $license_filename;
            
            // Construire la commande pour générer la licence
            // Utiliser la clé privée existante si elle existe, sinon en générer une nouvelle
            $keys_dir = __DIR__ . '/artisanSV/keys';
            $private_key_file = $keys_dir . '/artisan_priv_autogen.pem';
            $command_args = [
                '--client-id', escapeshellarg($client_id),
                '--pages', escapeshellarg((string)$quota_units),
                '--out', escapeshellarg($license_file_path)
            ];
            
            // Si une clé privée existe, l'utiliser
            if (file_exists($private_key_file)) {
                $command_args[] = '--private-key';
                $command_args[] = escapeshellarg($private_key_file);
            }
            
            // Construire la commande complète
            $command = escapeshellarg($venv_python) . ' ' . escapeshellarg($python_script) . ' ' . implode(' ', $command_args) . ' 2>&1';
            
            // Exécuter le script Python
            $output = shell_exec($command);
            
            if ($output === null) {
                throw new Exception("Erreur lors de l'exécution du script Python");
            }
            
            // Vérifier que le fichier de licence a été généré
            if (!file_exists($license_file_path)) {
                throw new Exception("La licence n'a pas été générée. Sortie: " . $output);
            }
            
            // Vérifier que le fichier n'est pas vide
            if (filesize($license_file_path) === 0) {
                throw new Exception("Le fichier de licence est vide");
            }
            
            // Nettoyage automatique : supprimer les fichiers de licence plus vieux que 3 jours
            // et supprimer les entrées correspondantes en base.
            $cutoff = time() - (3 * 24 * 60 * 60); // 3 jours
            $old_files = glob($licenses_dir . '/*.txt');
            if ($old_files !== false) {
                foreach ($old_files as $of) {
                    if (is_file($of) && filemtime($of) < $cutoff) {
                        @unlink($of);
                        try {
                            $stmt_clean = $db->prepare("DELETE FROM recharges WHERE encrypted_file_path = ?");
                            $stmt_clean->execute([$of]);
                        } catch (Exception $e) {
                            error_log("Erreur cleanup DB: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Chemin relatif pour stockage en base
            $encrypted_file_path = $license_file_path;

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
