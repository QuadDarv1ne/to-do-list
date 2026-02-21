<?php

namespace App\EventListener;

use App\Domain\Deal\Event\DealCreated;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Слушатель события DealCreated
 * 
 * Обрабатывает создание сделки:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления
 */
#[AsEventListener(event: DealCreated::class, method: 'onDealCreated')]
final class DealCreatedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onDealCreated(DealCreated $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Отправляем уведомления (опционально)
        $this->notifyManager($event);
    }

    private function logActivity(DealCreated $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('deal_created');
        $activityLog->setEventType('deal.created');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Создана сделка "%s" на сумму %s ₽ (Этап: %s)',
            $event->getTitle(),
            number_format((float) $event->getAmount(), 0, '.', ' '),
            $this->getStageLabel($event->getStage())
        ));

        // Устанавливаем пользователя (менеджера)
        // Примечание: нужно загрузить пользователя через UserRepository
        // Для простоты оставляем null или устанавливаем позже

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyManager(DealCreated $event): void
    {
        // Здесь можно отправить уведомление менеджеру
        // Например, через NotificationService
        // Пока оставляем заглушку для будущей реализации
    }

    private function getStageLabel(string $stage): string
    {
        return match($stage) {
            'lead' => 'Лид',
            'qualification' => 'Квалификация',
            'proposal' => 'Предложение',
            'negotiation' => 'Переговоры',
            'closing' => 'Закрытие',
            default => $stage,
        };
    }
}
