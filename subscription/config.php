<?php
/**
 * Configuration du système d'abonnement
 * Centralise toutes les variables et fonctions communes
 */

// Inclure config.php principal pour avoir accès à url() et prevent_direct_access()
// Toujours inclure si url() n'existe pas, ou si ROUTED n'est pas défini
if (!function_exists('url')) {
    require_once __DIR__ . '/../config.php';
}

// Configuration de la base de données SQLite
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/db/artisan_nd.db');
}
if (!defined('ADMIN_USERNAME')) {
    define('ADMIN_USERNAME', 'admin');
}

// Configuration des URLs
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_path = dirname($_SERVER['PHP_SELF'] ?? '/');
    define('SITE_URL', $protocol . '://' . $host . $script_path);
    define('BASE_URL', dirname($script_path));
}

// Pages du système (utiliser le routage)
if (!defined('LOGIN_PAGE')) {
    define('LOGIN_PAGE', '../index.php?page=login');
}
if (!defined('REGISTER_PAGE')) {
    define('REGISTER_PAGE', '../index.php?page=register');
}
if (!defined('DASHBOARD_PAGE')) {
    define('DASHBOARD_PAGE', '../index.php?page=dashboard');
}
if (!defined('ADMIN_PAGE')) {
    define('ADMIN_PAGE', '../index.php?page=admin');
}
if (!defined('RECHARGE_PAGE')) {
    define('RECHARGE_PAGE', '../index.php?page=recharge');
}
if (!defined('LOGOUT_PAGE')) {
    define('LOGOUT_PAGE', '../index.php?page=logout');
}

/**
 * Génère une URL pour une page du système d'abonnement
 * @param string $page Nom de la page
 * @param array $params Paramètres additionnels (optionnel)
 * @return string URL complète
 */
function subscription_url($page, $params = []) {
    $url = '../index.php?page=' . $page;
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    return $url;
}

// Configuration de sécurité
if (!defined('MIN_PASSWORD_LENGTH')) {
    define('MIN_PASSWORD_LENGTH', 8);
}
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}
if (!defined('LOGIN_LOCKOUT_TIME')) {
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes en secondes
}

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est administrateur
 */
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Redirige vers la page de connexion si non connecté
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . LOGIN_PAGE);
        exit;
    }
}

/**
 * Redirige vers le dashboard si déjà connecté
 */
function redirect_if_logged_in() {
    if (is_logged_in()) {
        header('Location: ' . DASHBOARD_PAGE);
        exit;
    }
}

/**
 * Connexion à la base de données SQLite
 */
function get_db_connection() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Créer le dossier db s'il n'existe pas
            $db_dir = dirname(DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0755, true);
            }
            
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Activer les clés étrangères
            $db->exec('PRAGMA foreign_keys = ON');
        } catch(PDOException $e) {
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
        }
    }
    
    return $db;
}

/**
 * Valide un email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un mot de passe (minimum 8 caractères, au moins une lettre et un chiffre)
 */
function validate_password($password) {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        return false;
    }
    // Au moins une lettre et un chiffre
    return preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

/**
 * Nettoie les données d'entrée
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Journalise une action utilisateur
 */
function log_activity($user_id, $action, $details = null) {
    try {
        $db = get_db_connection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $details, $ip_address]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la journalisation: " . $e->getMessage());
    }
}

/**
 * Vérifie les tentatives de connexion échouées
 */
function check_login_attempts($username) {
    $db = get_db_connection();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Vérifier les tentatives récentes depuis cette IP
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts 
        FROM activity_logs 
        WHERE action = 'login_failed' 
        AND ip_address = ? 
        AND created_at > datetime('now', '-' || ? || ' seconds')
    ");
    $stmt->execute([$ip_address, LOGIN_LOCKOUT_TIME]);
    $result = $stmt->fetch();
    
    return $result['attempts'] < MAX_LOGIN_ATTEMPTS;
}

/**
 * Vérifie si l'abonnement de l'utilisateur est actif
 * @param int $user_id ID de l'utilisateur
 * @return array ['active' => bool, 'message' => string, 'days_remaining' => int|null]
 */
function check_subscription_status($user_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT subscription_start, subscription_end, subscription_type FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['active' => false, 'message' => 'Utilisateur non trouvé', 'days_remaining' => null];
    }
    
    // Les admins ont toujours un abonnement actif
    if ($user['subscription_type'] === 'admin') {
        return ['active' => true, 'message' => 'Abonnement admin actif', 'days_remaining' => null];
    }
    
    // Vérifier si les dates d'abonnement existent
    if (empty($user['subscription_start']) || empty($user['subscription_end'])) {
        return ['active' => false, 'message' => 'Abonnement non configuré', 'days_remaining' => 0];
    }
    
    $today = new DateTime();
    $start_date = new DateTime($user['subscription_start']);
    $end_date = new DateTime($user['subscription_end']);
    
    // Vérifier si l'abonnement est dans la période valide
    if ($today >= $start_date && $today <= $end_date) {
        $days_remaining = $today->diff($end_date)->days;
        return [
            'active' => true, 
            'message' => 'Abonnement actif', 
            'days_remaining' => $days_remaining
        ];
    }
    
    // Abonnement expiré
    if ($today > $end_date) {
        $days_expired = $today->diff($end_date)->days;
        return [
            'active' => false, 
            'message' => 'Abonnement expiré', 
            'days_remaining' => -$days_expired
        ];
    }
    
    // Abonnement pas encore commencé
    return [
        'active' => false, 
        'message' => 'Abonnement pas encore actif', 
        'days_remaining' => null
    ];
}

/**
 * Récupère le statut d'abonnement de l'utilisateur connecté
 */
function get_current_user_subscription_status() {
    if (!is_logged_in()) {
        return null;
    }
    return check_subscription_status($_SESSION['user_id']);
}
?>