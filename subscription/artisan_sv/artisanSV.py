import os
import tkinter as tk
from tkinter import PhotoImage, messagebox, filedialog, Toplevel
import hashlib
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives import serialization, hashes
from datetime import datetime
import json
from src.server import EncryptionService  # Importation du service d'encryption
from PIL import Image, ImageTk  # Pour gérer l'icône

# Constants for storing password and key file paths
PASS_FILE = "ressources/pass.txt"

class EncryptionApp:
    def __init__(self):
        """Initialize the application and request password authentication."""
        self.root = tk.Tk()
        self.icon = PhotoImage(file="ressources/icons/icon0.png")  # Chargement de l'icône
        self.root.iconphoto(False, self.icon)  # Définir l'icône
        self.root.title("artisanSV")
        self.root.geometry("700x200")  # Taille de la fenêtre principale
        self.root.resizable(False, False)  # Fixer la taille de la fenêtre

        self.encryption_service = EncryptionService()  # Instanciation du service d'encryption
        self.authenticated = False  # Suivi de l'état d'authentification

        self.request_password()

    def hash_password(self, password):
        """Hash the given password using SHA-256."""
        return hashlib.sha256(password.encode()).hexdigest()

    def save_password(self, password):
        """Save the hashed password in a file."""
        os.makedirs("ressources", exist_ok=True)
        with open(PASS_FILE, "w") as f:
            f.write(self.hash_password(password))

    def check_password(self, password):
        """Check if the entered password matches the stored hash."""
        if not os.path.exists(PASS_FILE):
            return False
        with open(PASS_FILE, "r") as f:
            stored_hash = f.read().strip()
        return stored_hash == self.hash_password(password)

    def request_password(self):
        """Prompt the user to enter a password or set a new one."""
        def submit():
            entered_pass = entry.get()
            if os.path.exists(PASS_FILE):
                if self.check_password(entered_pass):
                    self.authenticated = True
                    login.destroy()
                    self.load_main_interface()
                else:
                    messagebox.showerror("Error", "Incorrect password", parent=login)
            else:
                self.save_password(entered_pass)
                messagebox.showinfo("Success", "Password saved.", parent=login)
                self.authenticated = True
                login.destroy()
                self.load_main_interface()

        login = Toplevel(self.root)
        login.iconphoto(False, self.icon)  # Définir l'icône
        login.title("Authentication")
        login.geometry("300x150")

        tk.Label(login, text="Enter your password:").pack(pady=5)
        entry = tk.Entry(login, show="*")
        entry.pack(pady=5)
        tk.Button(login, text="Submit", command=submit).pack(pady=10)

        login.protocol("WM_DELETE_WINDOW", self.root.quit)
        login.mainloop()

    def load_main_interface(self):
        """Load the main UI after successful authentication."""
        if self.authenticated:
            self.build_ui()

    def build_ui(self):
        """Set up the user interface."""
        frame = tk.Frame(self.root)
        frame.pack(pady=20)

        # Formulaire d'encryption
        tk.Label(frame, text="Phone Number:").grid(row=0, column=0, padx=10, pady=10)
        self.phone_entry = tk.Entry(frame, width=30)
        self.phone_entry.grid(row=0, column=1, padx=10, pady=10)

        tk.Label(frame, text="Email:").grid(row=1, column=0, padx=10, pady=10)
        self.email_entry = tk.Entry(frame, width=30)
        self.email_entry.grid(row=1, column=1, padx=10, pady=10)

        tk.Label(frame, text="Limit:").grid(row=2, column=0, padx=10, pady=10)
        self.limit_entry = tk.Entry(frame, width=30)
        self.limit_entry.grid(row=2, column=1, padx=10, pady=10)

        tk.Button(frame, text="Load Public Key", command=self.start_encryption).grid(row=3, columnspan=2, pady=10)

        # Ajout du bouton des paramètres avec l'icône
        self.settings_button = tk.Button(self.root, command=self.open_settings_frame)
        self.settings_button.place(x=10, y=10)

        settings_icon = Image.open("ressources/icons/settings.png")  # Assure-toi que le chemin est correct
        settings_icon = settings_icon.resize((36, 36))  # Redimensionner l'icône à 36x36
        settings_icon = ImageTk.PhotoImage(settings_icon)
        self.settings_button.config(image=settings_icon, relief=tk.FLAT)

        # Création du frame caché pour les paramètres
        self.settings_frame = tk.Frame(self.root, width=400, height=300, bg="lightgray")
        self.settings_frame.place(x=100, y=50)
        self.settings_frame.place_forget()  # Masquer initialement le frame

        # Button to open password change window
        self.change_password_button = tk.Button(self.root, text="setting", command=self.open_change_password_window)
        self.change_password_button.place(x=600, y=10)

    def open_settings_frame(self):
        """Ouvre ou ferme le frame des paramètres."""
        if self.settings_frame.winfo_ismapped():
            self.settings_frame.place_forget()
        else:
            self.settings_frame.place(x=100, y=50)  # Repositionner le frame quand il est ouvert

    def open_change_password_window(self):
        """Open the change password window."""
        change_password_window = Toplevel(self.root)
        change_password_window.iconphoto(False, self.icon)  # Définir l'icône
        change_password_window.title("Change Password")
        change_password_window.geometry("400x250")

        # Title section
        change_password_title = tk.Label(change_password_window, text="Change Your Password", font=("Arial", 16))
        change_password_title.pack(pady=10)

        # Section 1 - New password
        new_password_label = tk.Label(change_password_window, text="New Password:")
        new_password_label.pack(pady=5)
        new_password_entry = tk.Entry(change_password_window, show="*", width=30)
        new_password_entry.pack(pady=5)

        # Section 2 - Confirm password
        confirm_password_label = tk.Label(change_password_window, text="Confirm Password:")
        confirm_password_label.pack(pady=5)
        confirm_password_entry = tk.Entry(change_password_window, show="*", width=30)
        confirm_password_entry.pack(pady=5)

        # Submit button for changing password
        def submit_password_change():
            new_password = new_password_entry.get()
            confirm_password = confirm_password_entry.get()

            if new_password != confirm_password:
                messagebox.showerror("Error", "Passwords do not match!", parent=change_password_window)
                return

            if len(new_password) < 8:
                messagebox.showerror("Error", "Password must be at least 8 characters long!", parent=change_password_window)
                return

            self.save_password(new_password)
            messagebox.showinfo("Success", "Password updated successfully!", parent=change_password_window)
            change_password_window.destroy()

        submit_button = tk.Button(change_password_window, text="Submit", command=submit_password_change)
        submit_button.pack(pady=20)

    def update_password(self):
        """Logique pour mettre à jour le mot de passe."""
        new_password = self.new_password_entry.get()
        confirm_password = self.confirm_password_entry.get()

        if new_password != confirm_password:
            messagebox.showerror("Error", "Passwords do not match!")
            return

        if len(new_password) < 8:
            messagebox.showerror("Error", "Password must be at least 8 characters long!")
            return

        self.save_password(new_password)
        messagebox.showinfo("Success", "Password updated successfully!")

    def load_public_key(self):
        """Open a file dialog to select and load a public key."""
        file_path = filedialog.askopenfilename(title="Select a public key", filetypes=[("txt Files", "*.txt")])
        if file_path:
            with open(file_path, "r") as f:
                return f.read()
        return None

    def start_encryption(self):
        """Retrieve public key and encrypt the input data."""
        key_pem = self.load_public_key()
        if not key_pem:
            messagebox.showerror("Error", "No public key loaded!", parent=self.root)
            return

        phone = self.phone_entry.get()
        email = self.email_entry.get()
        try:
            limit = int(self.limit_entry.get())
        except ValueError:
            messagebox.showerror("Error", "Limit must be a number!", parent=self.root)
            return

        encrypted_message = self.encryption_service.encrypt_message(key_pem, phone, email, limit)

        # Créer un nom de fichier à partir de l'email
        file_name = f"{email}.bin"
        
        # Boîte de dialogue pour choisir le chemin d'enregistrement du fichier
        file_path = filedialog.asksaveasfilename(defaultextension=".bin", filetypes=[("Binary Files", "*.bin")], initialfile=file_name)

        if file_path:  # Si un chemin a été choisi
            try:
                with open(file_path, "wb") as file:
                    file.write(encrypted_message)
                messagebox.showinfo("Success", f"the key is saved as {file_name}!", parent=self.root)
            except Exception as e:
                messagebox.showerror("Error", f"An error occurred while saving the file: {e}", parent=self.root)
        else:
            messagebox.showwarning("Warning", "No file path selected. The file was not saved.", parent=self.root)


    def run(self):
        """Start the application."""
        self.root.mainloop()

if __name__ == "__main__":
    app = EncryptionApp()
    app.run()
