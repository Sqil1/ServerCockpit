// Version ultra-simplifiée
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('login-btn');
    const loadingIndicator = document.getElementById('loading');

    if (form && submitBtn && loadingIndicator) {
        form.addEventListener('submit', function() {
            submitBtn.style.display = 'none';
            loadingIndicator.style.display = 'flex';
        });
    }
});

// Toggle password (utile)
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('password-toggle-icon');

    if (passwordField && toggleIcon) {
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordField.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }
}

// Fermer les alertes (utile)
function closeAlert(alertId = 'error-alert') {
    const alert = document.getElementById(alertId);
    if (alert) {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }
}

window.togglePassword = togglePassword;
window.closeAlert = closeAlert;