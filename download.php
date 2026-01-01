<?php
require_once 'config.php';

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la configuration du système d'abonnement pour vérifier l'authentification
require_once __DIR__ . '/subscription/config.php';

// Vérifier si accès via routage ou direct
if (!defined('ROUTED') && !isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] !== 'download_file')) {
    // Si accès direct sans routage, rediriger vers 404
    header('Location: index.php?page=404');
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    // Sauvegarder l'URL demandée pour rediriger après connexion
    $_SESSION['redirect_after_login'] = url('download_file');
    
    // Rediriger vers la page de connexion avec un message
    header('Location: ' . url('login') . '&error=login_required&redirect=download');
    exit;
}

// Vérifier si le fichier existe
$file_path = __DIR__ . '/' . DOWNLOAD_FILE;

if (!file_exists($file_path)) {
    die("Fichier de téléchargement non disponible.");
}

// Récupérer les informations de l'utilisateur pour le log
$user_id = $_SESSION['user_id'] ?? 'unknown';
$username = $_SESSION['username'] ?? 'unknown';

// Enregistrer le téléchargement dans un fichier log
$log_file = __DIR__ . '/' . DOWNLOAD_LOG;
$log_entry = date('Y-m-d H:i:s') . " - Téléchargement de " . basename($file_path) . " - User ID: " . $user_id . " - Username: " . $username . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Journaliser l'action dans la base de données
if (function_exists('log_activity')) {
    log_activity($user_id, 'download_software', 'Téléchargement de ' . basename($file_path));
}

// Envoyer le fichier
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="artisan_nd_setup.deb"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>