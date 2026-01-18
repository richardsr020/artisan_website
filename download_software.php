<?php
require_once 'config.php';

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la configuration du système d'abonnement pour vérifier l'authentification
require_once __DIR__ . '/subscription/config.php';

// Vérifier si accès via routage ou direct
if (!defined('ROUTED') && !isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] !== 'download_software')) {
    header('Location: index.php?page=404');
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = url('download');
    header('Location: ' . url('login') . '&error=login_required&redirect=download');
    exit;
}

$software_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($software_id <= 0) {
    header('Location: index.php?page=404');
    exit;
}

$db = get_db_connection();
$stmt = $db->prepare('SELECT * FROM software WHERE id = ?');
$stmt->execute([$software_id]);
$software = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$software) {
    header('Location: index.php?page=404');
    exit;
}

$download_url = trim($software['download_url'] ?? '');
if (!empty($download_url) && filter_var($download_url, FILTER_VALIDATE_URL)) {
    $user_id = $_SESSION['user_id'] ?? 'unknown';
    if (function_exists('log_activity')) {
        $details = 'Redirection téléchargement: ' . $download_url . ' (ID: ' . $software_id . ')';
        log_activity($user_id, 'download_software_redirect', $details);
    }
    header('Location: ' . $download_url);
    exit;
}

$relative_path = $software['file_path'] ?? '';
$base_downloads_dir = realpath(__DIR__ . '/files/downloads');

$file_path = realpath(__DIR__ . '/' . ltrim($relative_path, '/'));

// Vérifier que le fichier est bien dans le dossier downloads
if (!$file_path || !$base_downloads_dir || strpos($file_path, $base_downloads_dir) !== 0) {
    header('Location: index.php?page=404');
    exit;
}

if (!file_exists($file_path)) {
    die('Fichier de téléchargement non disponible.');
}

$user_id = $_SESSION['user_id'] ?? 'unknown';

// Journaliser l'action dans la base de données
if (function_exists('log_activity')) {
    $details = 'Téléchargement ZIP: ' . ($software['file_name'] ?? basename($file_path)) . ' (ID: ' . $software_id . ')';
    log_activity($user_id, 'download_software_zip', $details);
}

$download_name = $software['file_name'] ?? basename($file_path);

$download_name_lower = strtolower($download_name);
$content_type = 'application/octet-stream';
if (str_ends_with($download_name_lower, '.zip')) {
    $content_type = 'application/zip';
} elseif (str_ends_with($download_name_lower, '.rar')) {
    $content_type = 'application/vnd.rar';
} elseif (str_ends_with($download_name_lower, '.7z')) {
    $content_type = 'application/x-7z-compressed';
} elseif (str_ends_with($download_name_lower, '.tar')) {
    $content_type = 'application/x-tar';
} elseif (str_ends_with($download_name_lower, '.tar.gz') || str_ends_with($download_name_lower, '.tgz')) {
    $content_type = 'application/gzip';
} elseif (str_ends_with($download_name_lower, '.gz')) {
    $content_type = 'application/gzip';
} elseif (str_ends_with($download_name_lower, '.bz2')) {
    $content_type = 'application/x-bzip2';
} elseif (str_ends_with($download_name_lower, '.xz')) {
    $content_type = 'application/x-xz';
}

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . basename($download_name) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
?>
