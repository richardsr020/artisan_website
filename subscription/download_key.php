<?php
// Protection contre l'accès direct - doit passer par index.php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once 'auth.php';

$db = get_db_connection();
$user_id = $_SESSION['user_id'];
$recharge_id = intval($_GET['id'] ?? 0);

if ($recharge_id <= 0) {
    header('Location: ' . DASHBOARD_PAGE . '&error=invalid_id');
    exit;
}

// Récupérer la recharge
$stmt = $db->prepare("SELECT * FROM recharges WHERE id = ? AND user_id = ?");
$stmt->execute([$recharge_id, $user_id]);
$recharge = $stmt->fetch();

if (!$recharge) {
    header('Location: ' . DASHBOARD_PAGE . '&error=recharge_not_found');
    exit;
}

// Vérifier que le fichier existe
if (empty($recharge['encrypted_file_path']) || !file_exists($recharge['encrypted_file_path'])) {
    header('Location: ' . DASHBOARD_PAGE . '&error=file_not_found');
    exit;
}

// Journaliser le téléchargement
log_activity($user_id, 'download_key', "Téléchargement de la clé pour recharge ID: $recharge_id");

// Envoyer le fichier
$file_path = $recharge['encrypted_file_path'];
$file_name = basename($file_path);

// Déterminer le Content-Type selon l'extension
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$content_type = 'application/octet-stream'; // par défaut
if ($file_ext === 'txt') {
    $content_type = 'text/plain';
} elseif ($file_ext === 'json') {
    $content_type = 'application/json';
}

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Nettoyer tout output buffer pour éviter du contenu HTML avant le fichier
while (ob_get_level()) {
    ob_end_clean();
}

readfile($file_path);
exit;
?>

