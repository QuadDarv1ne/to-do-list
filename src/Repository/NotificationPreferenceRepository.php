<?php

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 *
 * @method NotificationPreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method NotificationPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method NotificationPreference[]    findAll()
 * @method NotificationPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    public function findOneByUser(User $user): ?NotificationPreference
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(NotificationPreference $preference, bool $flush = true): void
    {
        $this->getEntityManager()->persist($preference);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NotificationPreference $preference, bool $flush = true): void
    {
        $this->getEntityManager()->remove($preference);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get or create preferences for user
     */
    public function getOrCreateForUser(User $user): NotificationPreference
    {
        $preference = $this->findOneByUser($user);
        
        if (!$preference) {
            $preference = new NotificationPreference();
            $preference->setUser($user);
            $this->save($preference);
        }
        
        return $preference;
    }
}
