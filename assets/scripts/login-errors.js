/**
 * Gestion simplifiée des erreurs de connexion
 * Validation côté client et animations
 */

class LoginErrorManager {
    constructor() {
        this.form = null;
        this.submitBtn = null;
        this.loadingIndicator = null;

        this.init();
    }

    /**
     * Initialise le gestionnaire d'erreurs
     */
    init() {
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupErrorHandling());
        } else {
            this.setupErrorHandling();
        }
    }

    /**
     * Configure la gestion d'erreurs
     */
    setupErrorHandling() {
        this.form = document.getElementById('login-form');
        this.submitBtn = document.getElementById('login-btn');
        this.loadingIndicator = document.getElementById('loading');

        if (!this.form || !this.submitBtn) {
            console.warn('Éléments de formulaire non trouvés pour la gestion d\'erreurs');
            return;
        }

        // Event listeners
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));

        console.log('Gestionnaire d\'erreurs de connexion initialisé');
    }

    /**
     * Gère la soumission du formulaire
     */
    handleFormSubmit(event) {
        // Afficher le loading
        this.showLoading();

        // Masquer les erreurs précédentes
        this.clearErrors();

        // Validation côté client
        const email = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        // Validation basique
        if (!this.validateForm(email, password)) {
            event.preventDefault();
            this.hideLoading();
            return false;
        }

        // Validation email
        if (!this.isValidEmail(email)) {
            event.preventDefault();
            this.showFieldError('username', 'Format d\'email invalide');
            this.hideLoading();
            return false;
        }

        // Le formulaire sera soumis normalement
        return true;
    }

    /**
     * Validation basique du formulaire
     */
    validateForm(email, password) {
        let isValid = true;

        if (!email) {
            this.showFieldError('username', 'L\'adresse email est requise');
            isValid = false;
        }

        if (!password) {
            this.showFieldError('password', 'Le mot de passe est requis');
            isValid = false;
        }

        if (!isValid) {
            this.showError('Veuillez remplir tous les champs obligatoires');
        }

        return isValid;
    }

    /**
     * Valide le format de l'email
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Affiche le loading
     */
    showLoading() {
        if (this.submitBtn && this.loadingIndicator) {
            this.submitBtn.style.display = 'none';
            this.loadingIndicator.style.display = 'flex';
        }
    }

    /**
     * Cache le loading
     */
    hideLoading() {
        if (this.submitBtn && this.loadingIndicator) {
            this.submitBtn.style.display = 'flex';
            this.loadingIndicator.style.display = 'none';
        }
    }

    /**
     * Affiche une erreur générale
     */
    showError(message) {
        // Supprimer les anciennes alertes
        this.removeExistingAlerts();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger fade-in';
        errorDiv.id = 'dynamic-error-alert';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <div class="error-content">
                <strong>Erreur</strong>
                <p>${message}</p>
            </div>
            <button type="button" class="close-alert" onclick="window.closeAlert('dynamic-error-alert')">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.form.insertBefore(errorDiv, this.form.firstChild);
    }

    /**
     * Affiche une erreur sur un champ spécifique
     */
    showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        // Ajouter la classe d'erreur
        field.classList.add('error');

        // Trouver ou créer l'élément d'erreur
        let errorElement = field.parentNode.querySelector('.error-message');
        if (!errorElement) {
            errorElement = document.createElement('span');
            errorElement.className = 'error-message';
            field.parentNode.appendChild(errorElement);
        }

        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    /**
     * Supprime toutes les erreurs
     */
    clearErrors() {
        // Supprimer les erreurs de champs
        document.querySelectorAll('.error-message').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });

        // Supprimer les classes d'erreur
        document.querySelectorAll('.form-control').forEach(el => {
            el.classList.remove('error');
        });

        // Supprimer les alertes dynamiques
        this.removeExistingAlerts();
    }

    /**
     * Supprime les alertes existantes
     */
    removeExistingAlerts() {
        const existingAlerts = document.querySelectorAll('#dynamic-error-alert');
        existingAlerts.forEach(alert => alert.remove());
    }

    /**
     * Gestion du succès de connexion
     */
    handleLoginSuccess() {
        this.showSuccessMessage('Connexion réussie ! Redirection...');
    }

    /**
     * Affiche un message de succès
     */
    showSuccessMessage(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'alert alert-success fade-in';
        successDiv.innerHTML = `
            <i class="fas fa-check-circle"></i>
            ${message}
        `;

        this.form.insertBefore(successDiv, this.form.firstChild);
    }
}

/**
 * Fonction pour basculer la visibilité du mot de passe
 */
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('password-toggle-icon');

    if (!passwordField || !toggleIcon) return;

    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

/**
 * Fonction globale pour fermer les alertes
 */
function closeAlert(alertId = 'error-alert') {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }
}

// Initialiser le gestionnaire d'erreurs
const loginErrorManager = new LoginErrorManager();

// Exposer globalement pour utilisation dans les templates
window.loginErrorManager = loginErrorManager;
window.togglePassword = togglePassword;
window.closeAlert = closeAlert;

// Export pour modules ES6
export default LoginErrorManager;