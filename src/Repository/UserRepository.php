<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche des utilisateurs avec filtres
     */
    public function findBySearchQuery(
        ?string $search = null,
        ?string $role = null,
        ?string $isActive = null
    ): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.datCre', 'DESC');

        if ($search) {
            $qb->andWhere('u.email LIKE :search OR u.username LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($role) {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%' . $role . '%');
        }

        if ($isActive) {
            $qb->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return $qb;
    }
}
