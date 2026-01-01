<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'config.php';

// Journaliser la déconnexion si l'utilisateur est connecté
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        log_activity($user_id, 'logout', "Déconnexion de l'utilisateur");
    }
}

// Détruire toutes les données de session
$_SESSION = array();

// Supprimer le cookie de session si il existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: ' . LOGIN_PAGE);
exit;