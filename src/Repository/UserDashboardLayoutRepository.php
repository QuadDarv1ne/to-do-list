<?php

namespace App\Repository;

use App\Entity\UserDashboardLayout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDashboardLayout>
 *
 * @method UserDashboardLayout|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserDashboardLayout|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserDashboardLayout[]    findAll()
 * @method UserDashboardLayout[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserDashboardLayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDashboardLayout::class);
    }

    /**
     * Найти layout пользователя
     */
    public function findByUser(int $userId): ?UserDashboardLayout
    {
        return $this->findOneBy(['user' => $userId]);
    }

    /**
     * Найти или создать layout для пользователя
     */
    public function findOrCreateForUser(int $userId): UserDashboardLayout
    {
        $layout = $this->findByUser($userId);

        if (!$layout) {
            // Создаём новый layout с настройками по умолчанию
            $layout = new UserDashboardLayout();
            // User будет установлен из сервиса
        }

        return $layout;
    }

    public function save(UserDashboardLayout $layout, bool $flush = true): void
    {
        $this->getEntityManager()->persist($layout);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserDashboardLayout $layout, bool $flush = true): void
    {
        $this->getEntityManager()->remove($layout);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Получить статистику использования виджетов
     */
    public function getWidgetUsageStats(): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb->select('l.widgets')
            ->where('l.widgets IS NOT NULL');

        $results = $qb->getQuery()->getResult();

        $widgetCount = [];

        foreach ($results as $result) {
            $widgets = $result['widgets'] ?? [];
            foreach ($widgets as $widget) {
                $widgetId = $widget['id'] ?? 'unknown';
                if (!isset($widgetCount[$widgetId])) {
                    $widgetCount[$widgetId] = 0;
                }
                $widgetCount[$widgetId]++;
            }
        }

        // Сортируем по популярности
        arsort($widgetCount);

        return $widgetCount;
    }

    /**
     * Получить количество layout'ов по темам
     */
    public function countByTheme(): array
    {
        $qb = $this->createQueryBuilder('l');

        $qb->select('l.theme')
            ->addSelect('COUNT(l.id) as count')
            ->groupBy('l.theme');

        return $qb->getQuery()->getResult();
    }
}
