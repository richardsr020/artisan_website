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
$stmt = $db->prepare("SELECT id, is_active, debt_suspended FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // Utilisateur supprimé
    session_destroy();
    header('Location: ' . LOGIN_PAGE);
    exit;
}

// Vérifier et suspendre les comptes en retard (sauf pour les admins)
if (!is_admin() && function_exists('check_and_suspend_overdue_accounts')) {
    check_and_suspend_overdue_accounts();
    // Recharger les données utilisateur après vérification
    $stmt = $db->prepare("SELECT id, is_active, debt_suspended FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
    $reason = '';
    if (isset($user['debt_suspended']) && (int)$user['debt_suspended'] === 1) {
        $reason = '&reason=debt';
    }
    header('Location: ' . subscription_url('suspended') . $reason);
    exit;
}

// Mettre à jour la dernière activité (optionnel, pour tracker les sessions actives)
// Vous pouvez ajouter une table last_activity si nécessaire
?>


