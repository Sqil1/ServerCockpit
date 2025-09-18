// Import des styles
import './styles/app.scss';

// Fonctions globales pour les templates
window.closeAlert = function(alertId = 'error-alert') {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }
};

window.togglePassword = function() {
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
};

class App {
    constructor() {
        this.init();
    }

    init() {
        // Attendre que le DOM soit prêt
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.ready());
        } else {
            this.ready();
        }
    }

    ready() {
        console.log('App prête');

        // Fonctionnalités de base (exclure le formulaire de login)
        this.initGeneralForms();
        this.initGeneralButtons();
        this.initNavigation();

        // Charger le login manager si on est sur la page de login
        if (document.getElementById('login-form')) {
            this.loadLoginManager();
        }
    }

    async loadLoginManager() {
        try {
            const { default: LoginErrorManager } = await import('./scripts/login-errors.js');
            window.loginErrorManager = new LoginErrorManager();
            console.log('Login manager chargé');
        } catch (error) {
            console.log('Pas de login manager disponible');
        }
    }

    // Améliorer les formulaires généraux (SAUF le login qui a son propre système)
    initGeneralForms() {
        const forms = document.querySelectorAll('form:not(#login-form)');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = 'Chargement...';

                    // Réactiver après 3 secondes (au cas où)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }, 3000);
                }
            });
        });
    }

    // Améliorer les boutons généraux (SAUF le bouton de login)
    initGeneralButtons() {
        const buttons = document.querySelectorAll('.btn:not(#login-btn)');

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    }

    // Navigation mobile pour le dashboard
    initNavigation() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.nav-menu');

        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', () => {
                navMenu.classList.toggle('mobile-open');
                mobileToggle.classList.toggle('active');
            });
        }

        // Fermer le menu mobile en cliquant sur un lien
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (navMenu) {
                    navMenu.classList.remove('mobile-open');
                }
                if (mobileToggle) {
                    mobileToggle.classList.remove('active');
                }
            });
        });
    }

    // Afficher une notification simple (pour les autres pages que login)
    showMessage(message, type = 'info') {
        // Ne pas interférer avec le système de login
        if (document.getElementById('login-form')) {
            console.log('Page de login détectée - pas de notification générale');
            return;
        }

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} fade-in`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            ${message}
            <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;float:right;cursor:pointer;">×</button>
        `;

        document.body.insertBefore(alert, document.body.firstChild);

        // Supprimer après 5 secondes
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    }

    // Fonctions utilitaires
    isLoginPage() {
        return document.getElementById('login-form') !== null;
    }

    isDashboardPage() {
        return document.querySelector('.dashboard') !== null;
    }
}

// Initialiser l'app
const app = new App();

// Exposer globalement
window.app = app;

// Exposer les fonctions du login pour compatibilité
if (typeof window.loginErrorManager !== 'undefined') {
    window.togglePassword = window.loginErrorManager.togglePassword;
    window.closeAlert = window.loginErrorManager.closeAlert;
}