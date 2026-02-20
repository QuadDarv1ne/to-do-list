<?php

namespace App\Repository;

use App\Entity\UserDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDevice>
 */
class UserDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDevice::class);
    }

    public function save(UserDevice $device, bool $flush = true): void
    {
        $this->getEntityManager()->persist($device);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserDevice $device, bool $flush = true): void
    {
        $this->getEntityManager()->remove($device);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
