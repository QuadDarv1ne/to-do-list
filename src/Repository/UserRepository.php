<?php

namespace App\Repository;

use App\Entity\User;
use App\Repository\Traits\CachedRepositoryTrait;
use App\Service\QueryCacheService;
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
    use CachedRepositoryTrait;
    
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
    
    public function setCacheService(QueryCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
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
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
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
        $totalUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $activeUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
            
        $adminUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :admin_role')
            ->setParameter('admin_role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();
                
        $managerUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :manager_role')
            ->setParameter('manager_role', '%ROLE_MANAGER%')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Get users who logged in today
        $today = new \DateTime();
        $todayStart = $today->format('Y-m-d 00:00:00');
        $todayEnd = $today->format('Y-m-d 23:59:59');
        
        $activeToday = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.lastLoginAt BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()
            ->getSingleScalarResult();
        
        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'admins' => $adminUsers,
            'managers' => $managerUsers,
            'active_today' => $activeToday,
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
