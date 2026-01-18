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

if (!defined('RECHARGE_UNIT_PRICE')) {
    define('RECHARGE_UNIT_PRICE', 0.01);
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $needle = (string)$needle;
        if ($needle == '') {
            return true;
        }
        $haystack = (string)$haystack;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
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

function ensure_database_initialized(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, email TEXT UNIQUE NOT NULL, company_name TEXT, phone TEXT, subscription_type TEXT DEFAULT 'basic', subscription_start DATE, subscription_end DATE, is_active BOOLEAN DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
 
    $db->exec("CREATE TABLE IF NOT EXISTS recharges (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, client_phone TEXT NOT NULL, client_email TEXT NOT NULL, quota_units INTEGER NOT NULL, amount DECIMAL(10, 2) NOT NULL, encrypted_file_path TEXT, transaction_id TEXT UNIQUE, status TEXT DEFAULT 'pending', error_message TEXT, recharge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, completed_date TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id))");
 
    $db->exec("CREATE TABLE IF NOT EXISTS payments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, amount DECIMAL(10, 2) NOT NULL, payment_method TEXT, transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status TEXT DEFAULT 'completed', FOREIGN KEY (user_id) REFERENCES users(id))");
 
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT NOT NULL, details TEXT, ip_address TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $db->exec("CREATE TABLE IF NOT EXISTS software (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, version TEXT, description TEXT, file_name TEXT NOT NULL, file_path TEXT NOT NULL, download_url TEXT, file_size INTEGER, uploaded_by_user_id INTEGER, is_active BOOLEAN DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id))");

    $stmt = $db->query("PRAGMA table_info(recharges)");
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $column_names = array_column($columns, 'name');
 
    $alterations = [];
    if (!in_array('encrypted_file_path', $column_names, true)) {
        $alterations[] = "ALTER TABLE recharges ADD COLUMN encrypted_file_path TEXT";
    }
    if (!in_array('error_message', $column_names, true)) {
        $alterations[] = "ALTER TABLE recharges ADD COLUMN error_message TEXT";
    }
    if (!in_array('completed_date', $column_names, true)) {
        $alterations[] = "ALTER TABLE recharges ADD COLUMN completed_date TIMESTAMP";
    }
 
    foreach ($alterations as $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            error_log("Erreur migration DB: " . $e->getMessage());
        }
    }

    // Migration table software: ajouter download_url si manquante
    try {
        $stmt = $db->query("PRAGMA table_info(software)");
        $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $column_names = array_column($columns, 'name');
        if (!in_array('download_url', $column_names, true)) {
            $db->exec("ALTER TABLE software ADD COLUMN download_url TEXT");
        }
    } catch (PDOException $e) {
        error_log("Erreur migration software: " . $e->getMessage());
    }

    $admin_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $admin_check->execute();
    if ((int)$admin_check->fetchColumn() === 0) {
        $admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_insert = $db->prepare("INSERT INTO users (username, password_hash, email, company_name, subscription_type, is_active) VALUES ('admin', ?, 'admin@artisan-nd.com', 'Artisan_ND Admin', 'admin', 1)");
        $admin_insert->execute([$admin_password_hash]);
    }
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

            ensure_database_initialized($db);
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