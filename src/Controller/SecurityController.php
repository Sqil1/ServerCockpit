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

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}