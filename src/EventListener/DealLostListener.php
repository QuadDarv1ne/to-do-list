<?php

namespace App\EventListener;

use App\Domain\Deal\Event\DealLost;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события DealLost
 * 
 * Обрабатывает отклонение сделки:
 * - Записывает запись в Activity Log
 * - Анализирует причину отказа
 * - Отправляет уведомления
 */
#[AsEventListener(event: DealLost::class, method: 'onDealLost')]
final class DealLostListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onDealLost(DealLost $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Анализируем причину отказа
        $this->analyzeLostReason($event);

        // Отправляем уведомления
        $this->notifyManager($event);
    }

    private function logActivity(DealLost $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('deal_lost');
        $activityLog->setEventType('deal.lost');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Сделка "%s" отклонена. Причина: %s',
            $event->getTitle(),
            $event->getReason()
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function analyzeLostReason(DealLost $event): void
    {
        // Здесь можно анализировать причины отказов
        // Например, собирать статистику по причинам
        // Пока оставляем заглушку для будущей реализации
    }

    private function notifyManager(DealLost $event): void
    {
        // Здесь можно отправить уведомление менеджеру
        // Например, через NotificationService
        // Пока оставляем заглушку для будущей реализации
    }
}
