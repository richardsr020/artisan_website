# Guide d'installation - Système d'abonnement Artisan_ND

## 🚀 Installation initiale

### 1. Initialiser la base de données

Avant d'utiliser le système d'inscription et de connexion, vous devez initialiser la base de données SQLite :

```bash
cd subscription
php init_db.php
```

Ce script va :
- Créer le dossier `db/` s'il n'existe pas
- Créer la base de données SQLite `artisan_nd.db`
- Créer toutes les tables nécessaires (users, recharges, payments, activity_logs)
- Créer l'utilisateur administrateur par défaut

### 2. Identifiants administrateur par défaut

Après l'initialisation, vous pouvez vous connecter avec :
- **Username:** `admin`
- **Password:** `admin123`

⚠️ **Important:** Changez le mot de passe admin immédiatement après la première connexion !

### 3. Structure des fichiers

```
subscription/
├── config.php          # Configuration principale (DB, sécurité, fonctions)
├── auth.php            # Protection des pages (à inclure dans les pages protégées)
├── init_db.php         # Script d'initialisation de la base de données
├── login.php           # Page de connexion
├── register.php        # Page d'inscription
├── logout.php          # Déconnexion
├── dashboard.php       # Tableau de bord partenaire
├── admin.php           # Panel administrateur
├── recharge.php        # Formulaire de recharge
├── process_recharge.php # Traitement des recharges
├── database.sql        # Structure SQL de la base de données
└── db/
    └── artisan_nd.db   # Base de données SQLite (créée automatiquement)
```

## 🔐 Sécurité

### Fonctionnalités de sécurité implémentées

1. **Hachage des mots de passe:** Utilisation de `password_hash()` avec l'algorithme bcrypt
2. **Protection contre les attaques par force brute:** Limitation à 5 tentatives de connexion par IP (blocage de 15 minutes)
3. **Validation des entrées:** Nettoyage et validation de toutes les données utilisateur
4. **Sessions sécurisées:** Régénération de l'ID de session après connexion
5. **Journalisation:** Toutes les actions importantes sont journalisées (connexions, déconnexions, recharges)

### Configuration de sécurité

Les paramètres de sécurité sont définis dans `config.php` :

```php
define('MIN_PASSWORD_LENGTH', 8);        // Longueur minimale du mot de passe
define('MAX_LOGIN_ATTEMPTS', 5);          // Nombre max de tentatives de connexion
define('LOGIN_LOCKOUT_TIME', 900);        // Durée du blocage (en secondes)
```

## 📝 Utilisation

### Pour les développeurs

#### Protéger une page nécessitant une connexion

```php
<?php
require_once 'auth.php';
// Votre code ici...
?>
```

#### Vérifier si l'utilisateur est admin

```php
<?php
require_once 'auth.php';

if (is_admin()) {
    // Code réservé aux admins
}
?>
```

#### Journaliser une action

```php
log_activity($user_id, 'action_name', 'Détails de l\'action');
```

### Pour les utilisateurs

1. **Inscription:** Accédez à `subscription/register.php`
2. **Connexion:** Accédez à `subscription/login.php`
3. **Dashboard:** Après connexion, redirection automatique vers `dashboard.php`

## 🗄️ Structure de la base de données

### Table `users`
- `id`: Identifiant unique
- `username`: Nom d'utilisateur (unique)
- `password_hash`: Mot de passe hashé
- `email`: Email (unique)
- `company_name`: Nom de l'entreprise
- `phone`: Téléphone
- `subscription_type`: Type d'abonnement (basic, premium, admin)
- `subscription_start`: Date de début d'abonnement
- `subscription_end`: Date de fin d'abonnement
- `is_active`: Statut actif/inactif
- `created_at`: Date de création

### Table `recharges`
- `id`: Identifiant unique
- `user_id`: ID du partenaire
- `client_code`: Code du client final
- `amount`: Montant de la recharge
- `quota_units`: Nombre d'unités de quota
- `transaction_id`: ID de transaction unique
- `status`: Statut (pending, completed, failed)
- `recharge_date`: Date de la recharge

### Table `payments`
- `id`: Identifiant unique
- `user_id`: ID du partenaire
- `amount`: Montant du paiement
- `payment_method`: Méthode de paiement
- `status`: Statut du paiement
- `transaction_date`: Date de la transaction

### Table `activity_logs`
- `id`: Identifiant unique
- `user_id`: ID de l'utilisateur (peut être null)
- `action`: Type d'action (login, logout, register, etc.)
- `details`: Détails de l'action
- `ip_address`: Adresse IP
- `created_at`: Date et heure

## 🔧 Dépannage

### La base de données ne se crée pas

1. Vérifiez les permissions du dossier `subscription/`
2. Assurez-vous que PHP a les droits d'écriture
3. Vérifiez que l'extension SQLite est activée dans PHP

### Erreur "Erreur de connexion à la base de données"

1. Vérifiez que le dossier `db/` existe et est accessible en écriture
2. Vérifiez les permissions du fichier `artisan_nd.db`
3. Vérifiez que l'extension PDO SQLite est activée

### Impossible de se connecter

1. Vérifiez que la base de données a été initialisée (`php init_db.php`)
2. Vérifiez que l'utilisateur existe dans la table `users`
3. Vérifiez que `is_active = 1` pour l'utilisateur

## 📞 Support

Pour toute question ou problème, contactez l'équipe de développement.


