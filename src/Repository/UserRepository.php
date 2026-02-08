<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findManagers(): array
    {
        return $this->findByRole('ROLE_MANAGER');
    }

    public function findAdmins(): array
    {
        return $this->findByRole('ROLE_ADMIN');
    }

    public function findUserByEmailOrUsername(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :identifier OR u.username = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        return [
            'total' => $qb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult(),
            'active' => $qb->select('COUNT(u.id)')
                ->andWhere('u.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'admins' => $qb->select('COUNT(u.id)')
                ->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                ->setParameter('role', json_encode('ROLE_ADMIN'))
                ->getQuery()
                ->getSingleScalarResult(),
            'managers' => $qb->select('COUNT(u.id)')
                ->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
                ->setParameter('role', json_encode('ROLE_MANAGER'))
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    public function lockUser(User $user, int $minutes = 15): void
    {
        $lockedUntil = new \DateTime();
        $lockedUntil->modify("+{$minutes} minutes");
        
        $user->setLockedUntil($lockedUntil);
        $user->setFailedLoginAttempts(0);
        
        $this->getEntityManager()->flush();
    }

    public function unlockUser(User $user): void
    {
        $user->setLockedUntil(null);
        $user->setFailedLoginAttempts(0);
        
        $this->getEntityManager()->flush();
    }
}
