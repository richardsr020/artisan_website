# Système de Recharge de Quotas - Documentation

## 📋 Vue d'ensemble

Le système de recharge permet aux partenaires de générer des clés cryptographiques pour recharger les quotas des clients finaux. Le système utilise Python pour le cryptage et PHP pour l'interface web et le stockage.

## 🔧 Configuration initiale

### 1. Mettre à jour la base de données

Si vous avez une base de données existante avec l'ancienne structure, vous avez deux options :

**Option A : Nettoyer la table (recommandé)**
```bash
cd subscription
php cleanup_db.php
```
Ce script supprime les colonnes inutiles (client_phone, client_email, public_key_pem, encrypted_message) qui sont déjà dans le fichier .bin.

**Option B : Ajouter seulement les colonnes manquantes**
```bash
cd subscription
php migrate_db.php
```

Ce script ajoutera les colonnes nécessaires à la table `recharges` :
- `encrypted_file_path` : Chemin du fichier .bin généré
- `error_message` : Message d'erreur si échec
- `completed_date` : Date de complétion

**Note:** Les informations comme phone, email, limit, date sont déjà dans le fichier .bin crypté, donc pas besoin de les stocker séparément dans la base de données.

### 2. Vérifier les dépendances Python

Assurez-vous que Python 3 et les bibliothèques nécessaires sont installées :

```bash
cd subscription/artisan_sv
pip3 install -r requirements.txt
```

Les dépendances requises :
- `cryptography` : Pour le cryptage RSA

### 3. Permissions

Assurez-vous que le dossier `encrypted_keys` est accessible en écriture :

```bash
chmod 755 subscription/encrypted_keys
```

## 🚀 Utilisation

### Interface Web

1. **Accéder au formulaire de recharge** :
   - Connectez-vous à votre compte partenaire
   - Allez dans "Tableau de bord"
   - Cliquez sur "Nouvelle recharge" ou "Accéder au formulaire de recharge"

2. **Remplir le formulaire** :
   - **Code Client** : Identifiant unique du client
   - **Numéro de Téléphone** : Téléphone du client
   - **Email Client** : Email du client
   - **Nombre d'Unités** : Nombre d'unités de quota à recharger
   - **Montant** : Montant en FCFA
   - **Clé Publique** : Fichier .txt contenant la clé publique PEM du client

3. **Soumettre** :
   - Le système va :
     - Valider les données
     - Appeler le script Python pour crypter
     - Générer le fichier .bin
     - Stocker toutes les informations dans la base de données

4. **Télécharger la clé** :
   - Dans le tableau de bord, vous pouvez voir toutes vos recharges
   - Cliquez sur "📥 Télécharger" pour récupérer le fichier .bin

## 🔐 Format de la clé publique

Le fichier de clé publique doit être au format PEM :

```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
-----END PUBLIC KEY-----
```

## 📁 Structure des fichiers

```
subscription/
├── recharge.php              # Interface web de recharge
├── process_recharge.php       # Traitement PHP (appelle Python)
├── download_key.php          # Téléchargement des fichiers .bin
├── encrypted_keys/           # Dossier des fichiers .bin générés
│   └── client_email_RCH_*.bin
├── artisan_sv/
│   ├── server.py             # Script Python de cryptage
│   └── requirements.txt      # Dépendances Python
└── database.sql              # Structure de la base de données
```

## 🔄 Flux de traitement

1. **Utilisateur soumet le formulaire** → `recharge.php`
2. **Validation PHP** → `process_recharge.php`
3. **Appel Python** → `server.py` avec JSON en argument
4. **Cryptage** → Python génère le message crypté
5. **Stockage** → PHP sauvegarde :
   - Le fichier .bin dans `encrypted_keys/`
   - Les données dans la table `recharges`
6. **Retour** → Redirection vers le dashboard avec message de succès

## 🐍 Communication PHP ↔ Python

Le script Python accepte des arguments JSON en ligne de commande :

```bash
python3 server.py '{"public_key_pem":"...","phone_number":"...","email":"...","limit":100}'
```

Le script retourne un JSON :
```json
{
  "success": true,
  "encrypted_message": "base64_encoded_string",
  "phone": "...",
  "email": "...",
  "limit": 100,
  "date": "2024-01-15"
}
```

## 📊 Données stockées

Chaque recharge stocke uniquement les informations essentielles :
- Code client
- Montant et unités de quota
- Chemin du fichier .bin généré
- Statut et dates
- Transaction ID unique

**Note:** Les informations détaillées (téléphone, email, clé publique, message crypté) sont déjà contenues dans le fichier .bin crypté, donc pas besoin de les dupliquer dans la base de données.

## ⚠️ Dépannage

### Erreur "Python script not found"
- Vérifiez que `server.py` existe dans `subscription/artisan_sv/`
- Vérifiez les permissions d'exécution

### Erreur "Cannot write encrypted file"
- Vérifiez les permissions du dossier `encrypted_keys/`
- Vérifiez l'espace disque disponible

### Erreur "Invalid public key"
- Vérifiez que le fichier contient bien une clé PEM valide
- Format : `-----BEGIN PUBLIC KEY-----` ... `-----END PUBLIC KEY-----`

### Erreur de cryptage
- Vérifiez que la clé publique est valide
- Vérifiez que les données (phone, email, limit) sont correctes
- Consultez les logs PHP pour plus de détails

## 🔒 Sécurité

- Les fichiers .bin sont stockés dans un dossier protégé
- Seuls les partenaires authentifiés peuvent générer des recharges
- Chaque recharge est tracée dans la base de données
- Les clés publiques sont stockées pour audit
- Les messages cryptés utilisent RSA-OAEP avec SHA-256

## 📝 Notes

- Le système ne modifie pas `server.py`, il l'utilise tel quel
- Les fichiers .bin sont nommés : `{email}_{transaction_id}.bin`
- Chaque transaction a un ID unique pour éviter les doublons
- Les erreurs sont journalisées dans `activity_logs`

