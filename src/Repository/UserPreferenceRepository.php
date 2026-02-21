<?php

namespace App\Repository;

use App\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPreference>
 *
 * @method UserPreference|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserPreference|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPreference[]    findAll()
 * @method UserPreference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    /**
     * Найти настройку пользователя по ключу
     */
    public function findByUserAndKey(int $userId, string $key): ?UserPreference
    {
        return $this->findOneBy([
            'user' => $userId,
            'preferenceKey' => $key,
        ]);
    }

    /**
     * Найти или создать настройку
     */
    public function findOrCreate(int $userId, string $key): UserPreference
    {
        $preference = $this->findByUserAndKey($userId, $key);

        if (!$preference) {
            $preference = new UserPreference();
            $preference->setPreferenceKey($key);
            // User будет установлен из сервиса
        }

        return $preference;
    }

    /**
     * Получить все настройки пользователя
     *
     * @return UserPreference[]
     */
    public function findAllByUser(int $userId): array
    {
        return $this->findBy(['user' => $userId], ['preferenceKey' => 'ASC']);
    }

    /**
     * Получить значение настройки
     */
    public function getValue(int $userId, string $key, mixed $default = null): mixed
    {
        $preference = $this->findByUserAndKey($userId, $key);

        if (!$preference || !$preference->getPreferenceValue()) {
            return $default;
        }

        return $preference->getPreferenceValue();
    }

    /**
     * Установить значение настройки
     */
    public function setValue(int $userId, User $user, string $key, array $value): UserPreference
    {
        $preference = $this->findByUserAndKey($userId, $key);

        if (!$preference) {
            $preference = new UserPreference();
            $preference->setUser($user);
            $preference->setPreferenceKey($key);
        }

        $preference->setPreferenceValue($value);

        $this->save($preference);

        return $preference;
    }

    public function save(UserPreference $preference, bool $flush = true): void
    {
        $this->getEntityManager()->persist($preference);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserPreference $preference, bool $flush = true): void
    {
        $this->getEntityManager()->remove($preference);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Удалить настройку пользователя
     */
    public function removeByUserAndKey(int $userId, string $key): bool
    {
        $preference = $this->findByUserAndKey($userId, $key);

        if (!$preference) {
            return false;
        }

        $this->remove($preference);

        return true;
    }

    /**
     * Получить статистику использования настроек
     */
    public function getUsageStats(): array
    {
        $qb = $this->createQueryBuilder('p');

        $qb->select('p.preferenceKey')
            ->addSelect('COUNT(p.id) as count')
            ->groupBy('p.preferenceKey')
            ->orderBy('count', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
