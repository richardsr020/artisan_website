from cryptography.hazmat.primitives.asymmetric import rsa, padding
from cryptography.hazmat.primitives import serialization, hashes
from datetime import datetime
import json
import sys
import base64

class EncryptionService:
    def __init__(self):
        """Initialisation de la classe d'encryption"""
        pass

    def encrypt_message(self, public_key_pem, phone_number, email, limit):
        """
        Encrypte un message avec la clé publique de l'utilisateur, le numéro de téléphone,
        la limite et la date d'aujourd'hui, tout en formattant le message dans des accolades.

        Args:
        - public_key_pem (str): La clé publique de l'utilisateur en format PEM.
        - phone_number (str): Le numéro de téléphone de l'utilisateur.
        - limit (int): La limite à ajouter dans le message.

        Returns:
        - encrypted_message (bytes): Le message crypté.
        """
        # Récupère la clé publique de l'utilisateur
        public_key = serialization.load_pem_public_key(public_key_pem.encode())

        # Récupère la date d'aujourd'hui
        today_date = datetime.today().strftime('%Y-%m-%d')

        # Crée le message à chiffrer

        message = json.dumps({
            "phone": phone_number,
            "email": email,
            "limit": limit,
            "date": today_date
        })


        # Encrypte le message
        encrypted_message = public_key.encrypt(
            message.encode(),
            padding.OAEP(
                mgf=padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None
            )
        )
        
        return encrypted_message

    def encrypt_message_base64(self, public_key_pem, phone_number, email, limit):
        """
        Encrypte un message et retourne le résultat en base64 pour faciliter le transfert via JSON.
        
        Args:
        - public_key_pem (str): La clé publique de l'utilisateur en format PEM.
        - phone_number (str): Le numéro de téléphone de l'utilisateur.
        - email (str): L'email de l'utilisateur.
        - limit (int): La limite à ajouter dans le message.

        Returns:
        - encrypted_message_base64 (str): Le message crypté encodé en base64.
        """
        encrypted_message = self.encrypt_message(public_key_pem, phone_number, email, limit)
        return base64.b64encode(encrypted_message).decode('utf-8')


# Interface en ligne de commande pour PHP
if __name__ == "__main__":
    if len(sys.argv) > 1:
        # Mode ligne de commande pour PHP
        try:
            # Récupérer les arguments JSON
            input_data = json.loads(sys.argv[1])
            
            public_key_pem = input_data.get('public_key_pem')
            phone_number = input_data.get('phone_number')
            email = input_data.get('email')
            limit = input_data.get('limit')
            
            if not all([public_key_pem, phone_number, email, limit]):
                result = {
                    'success': False,
                    'error': 'Missing required parameters'
                }
                print(json.dumps(result))
                sys.exit(1)
            
            # Initialiser le service
            encryption_service = EncryptionService()
            
            # Encrypter le message
            encrypted_message_base64 = encryption_service.encrypt_message_base64(
                public_key_pem, phone_number, email, int(limit)
            )
            
            # Retourner le résultat en JSON
            result = {
                'success': True,
                'encrypted_message': encrypted_message_base64,
                'phone': phone_number,
                'email': email,
                'limit': limit,
                'date': datetime.today().strftime('%Y-%m-%d')
            }
            
            print(json.dumps(result))
            
        except Exception as e:
            result = {
                'success': False,
                'error': str(e)
            }
            print(json.dumps(result))
            sys.exit(1)


# # Exemple d'utilisation de la classe
# if __name__ == "__main__":
#     # Exemple de clé publique PEM (pour une vraie utilisation, récupérer la clé publique d'un utilisateur)
#     example_public_key_pem = """-----BEGIN PUBLIC KEY-----
# MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAthFzb/mhTdTompresZuI
# q+CaG9PMc9Iok0VyV1XKXYudRpKFgsC8Ld6h4FR3KBF5wHqNk3441TiCkV3u9S6U
# +nbkKtbIVLwUENMzkJlaLvrw6JFjo9EWST8cSL9WHf0mQWqFtuRTwXqaz9DSaMZp
# W032QzZmD1Elt1l7fVRUtoakZAu6SWOHo3kqX+Z2V+1d1y/E1Es3ePrJ483KGYJR
# EqHLK/7/B05zV2FM9256KpV8DwLGbpIR6vUrSiYlhVEOcTsvmptP6RW0NBM1gmoZ
# EAubypZVhycVTdARbfLdYfsOJrWh4onUOL3asCnIQrICVZEo+KUMp1R74PlpIdKm
# fQIDAQAB
# -----END PUBLIC KEY-----
# """
    
#     phone_number = "0840149027"
#     limit = 100
#     email = "example@example.com"  # Placeholder, utiliser l'email de l'utilisateur

#     # Initialisation du service d'encryption
#     encryption_service = EncryptionService()

#     # Encryption du message
#     encrypted_message = encryption_service.encrypt_message(example_public_key_pem, phone_number, email, limit)

#     # Affichage du message crypté (en bytes)
#     with open ("cipherKey.bin","wb") as file:
#         file.write(encrypted_message)
#     # print(f"Message crypté : {encrypted_message}")
