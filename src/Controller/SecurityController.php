<?php
namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AuthenticationMessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        UserRepository $userRepository,
        AuthenticationMessageService $messageService
    ): Response {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $errorMessage = null;
        if ($error) {
            $user = $lastUsername ? $userRepository->findOneBy(['email' => strtolower(trim($lastUsername))]) : null;
            $errorMessage = $messageService->getErrorMessage($error->getMessageKey(),$user);
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'error_message' => $errorMessage,
        ]);
    }

    private function getUserStatus($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'isActive' => $user->isActive(),
            'isLocked' => $user->isLocked(),
            'loginAttempts' => $user->getLoginAttempts(),
            'maxAttempts' => 5
        ];
    }

    private function getCustomErrorMessage(string $messageKey, ?array $userStatus): string
    {
        // Messages basés sur le statut de l'utilisateur
        if ($userStatus) {
            if (!$userStatus['isActive']) {
                return 'Votre compte est désactivé. Contactez l\'administrateur.';
            }

            if ($userStatus['isLocked']) {
                return 'Compte verrouillé après ' . $userStatus['loginAttempts'] . ' tentatives. Contactez l\'administrateur.';
            }

            // Avertir si proche du verrouillage
            if ($userStatus['loginAttempts'] >= 3) {
                $remaining = $userStatus['maxAttempts'] - $userStatus['loginAttempts'];
                return "Identifiants incorrects. Attention : plus que {$remaining} tentative(s) avant verrouillage.";
            }
        }

        // Messages standards selon le type d'erreur
        return match($messageKey) {
            'Invalid credentials.' => 'Email ou mot de passe incorrect',
            'Username could not be found.' => 'Aucun compte trouvé avec cet email',
            'Bad credentials.' => 'Identifiants invalides',
            'Account is disabled.' => 'Votre compte a été désactivé',
            'Account is locked.' => 'Votre compte est temporairement verrouillé',
            default => 'Erreur de connexion. Veuillez réessayer.'
        };
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}