-- Création des tables pour le système d'abonnement

-- Table des utilisateurs (partenaires)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    company_name TEXT,
    phone TEXT,
    subscription_type TEXT DEFAULT 'basic',
    subscription_start DATE,
    subscription_end DATE,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des recharges
CREATE TABLE IF NOT EXISTS recharges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    client_phone TEXT NOT NULL,
    client_email TEXT NOT NULL,
    quota_units INTEGER NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    encrypted_file_path TEXT,
    transaction_id TEXT UNIQUE,
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    recharge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_date TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des paiements
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'completed',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des logs d'activité
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Note: L'utilisateur admin sera créé par le script init_db.php avec le mot de passe hashé correctement