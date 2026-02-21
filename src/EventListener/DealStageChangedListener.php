<?php

namespace App\EventListener;

use App\Domain\Deal\Event\DealStageChanged;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события DealStageChanged
 * 
 * Обрабатывает изменение этапа сделки:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления
 */
#[AsEventListener(event: DealStageChanged::class, method: 'onDealStageChanged')]
final class DealStageChangedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onDealStageChanged(DealStageChanged $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Отправляем уведомления при переходе на важные этапы
        $this->notifyIfImportant($event);
    }

    private function logActivity(DealStageChanged $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('deal_stage_changed');
        $activityLog->setEventType('deal.stage_changed');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Сделка "%s" перешла с этапа "%s" на этап "%s"',
            $event->getTitle(),
            $this->getStageLabel($event->getOldStage()),
            $this->getStageLabel($event->getNewStage())
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyIfImportant(DealStageChanged $event): void
    {
        // Отправляем уведомление при переходе на финальные этапы
        if (in_array($event->getNewStage(), ['negotiation', 'closing'])) {
            // Здесь можно отправить уведомление
            // Например, через NotificationService
        }
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
