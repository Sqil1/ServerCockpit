<?php
namespace App\Service;

use App\Entity\User;

class AuthenticationMessageService
{
    public function getErrorMessage(string $messageKey, ?User $user = null): string
    {
        // Vérifications sur l'utilisateur
        if ($user) {
            if (!$user->isActive()) {
                return 'Votre compte est désactivé. Contactez l\'administrateur.';
            }

            if ($user->isLocked()) {
                return 'Compte verrouillé après ' . $user->getLoginAttempts() . ' tentatives. Contactez l\'administrateur.';
            }
        }

        // Messages standards
        return match($messageKey) {
            'Invalid credentials.' => 'Email ou mot de passe incorrect',
            default => 'Erreur de connexion. Veuillez réessayer.'
        };
    }
}