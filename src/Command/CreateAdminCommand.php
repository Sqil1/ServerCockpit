<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur administrateur'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔐 Création d\'un utilisateur administrateur');

        // Collecter les informations
        $email = $io->ask('Email', 'admin@dashboard.local', function ($value) {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Veuillez saisir un email valide');
            }
            return $value;
        });

        $username = $io->ask('Nom d\'utilisateur', 'admin', function ($value) {
            if (empty($value) || strlen($value) < 3) {
                throw new \Exception('Le nom d\'utilisateur doit faire au moins 3 caractères');
            }
            return $value;
        });

        $firstName = $io->ask('Prénom', 'Administrateur');
        $lastName = $io->ask('Nom de famille', 'Système');

        $password = $io->askHidden('Mot de passe', function ($value) {
            if (empty($value) || strlen($value) < 6) {
                throw new \Exception('Le mot de passe doit faire au moins 6 caractères');
            }
            return $value;
        });

        $confirmPassword = $io->askHidden('Confirmer le mot de passe');

        if ($password !== $confirmPassword) {
            $io->error('Les mots de passe ne correspondent pas');
            return Command::FAILURE;
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->error('Un utilisateur avec cet email existe déjà');
            return Command::FAILURE;
        }

        $existingUsername = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existingUsername) {
            $io->error('Ce nom d\'utilisateur est déjà pris');
            return Command::FAILURE;
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($email)
            ->setUsername($username)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setIsActive(true);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Validation
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }
            return Command::FAILURE;
        }

        // Sauvegarder
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success([
            'Utilisateur administrateur créé avec succès !',
            "Email: {$email}",
            "Username: {$username}",
            "Nom: {$user->getFullName()}",
            "Rôles: " . implode(', ', $user->getRoles())
        ]);

        $io->note('Vous pouvez maintenant vous connecter avec ces identifiants.');

        return Command::SUCCESS;
    }
}