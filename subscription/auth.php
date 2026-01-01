<?php
/**
 * Fichier d'authentification
 * À inclure au début des pages nécessitant une connexion
 */

require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    // Sauvegarder l'URL demandée pour rediriger après connexion
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Sauvegarder l'URL demandée pour rediriger après connexion
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    
    header('Location: ' . LOGIN_PAGE);
    exit;
}

// Vérifier que l'utilisateur existe toujours et est actif
$db = get_db_connection();
$stmt = $db->prepare("SELECT id, is_active FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    // Utilisateur supprimé ou désactivé
    session_destroy();
    header('Location: ' . LOGIN_PAGE . '?error=account_inactive');
    exit;
}

// Mettre à jour la dernière activité (optionnel, pour tracker les sessions actives)
// Vous pouvez ajouter une table last_activity si nécessaire
?>


