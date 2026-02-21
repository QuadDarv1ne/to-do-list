<?php

namespace App\EventListener;

use App\Domain\Deal\Event\DealWon;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события DealWon
 * 
 * Обрабатывает выигрыш сделки:
 * - Записывает запись в Activity Log
 * - Обновляет статистику
 * - Отправляет уведомления
 */
#[AsEventListener(event: DealWon::class, method: 'onDealWon')]
final class DealWonListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onDealWon(DealWon $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Обновляем статистику менеджера
        $this->updateManagerStats($event);

        // Отправляем уведомления
        $this->notifySuccess($event);
    }

    private function logActivity(DealWon $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('deal_won');
        $activityLog->setEventType('deal.won');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Сделка "%s" выиграна! Сумма: %s ₽',
            $event->getTitle(),
            number_format((float) $event->getAmount(), 0, '.', ' ')
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function updateManagerStats(DealWon $event): void
    {
        // Здесь можно обновить статистику менеджера
        // Например, увеличить счётчик выигранных сделок
        // Пока оставляем заглушку для будущей реализации
    }

    private function notifySuccess(DealWon $event): void
    {
        // Здесь можно отправить уведомление о победе
        // Например, через NotificationService
        // Пока оставляем заглушку для будущей реализации
    }
}
