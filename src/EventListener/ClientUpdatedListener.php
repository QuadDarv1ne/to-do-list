<?php

namespace App\EventListener;

use App\Domain\Client\Event\ClientUpdated;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события ClientUpdated
 * 
 * Обрабатывает обновление клиента:
 * - Записывает запись в Activity Log
 * - Отправляет уведомления при важных изменениях
 */
#[AsEventListener(event: ClientUpdated::class, method: 'onClientUpdated')]
final class ClientUpdatedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onClientUpdated(ClientUpdated $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Отправляем уведомления при важных изменениях
        $this->notifyIfImportant($event);
    }

    private function logActivity(ClientUpdated $event): void
    {
        $changedFields = $event->getChangedFields();
        $descriptions = [];

        foreach ($changedFields as $field => $change) {
            $descriptions[] = sprintf(
                '%s: %s → %s',
                $this->getFieldLabel($field),
                $this->getValueLabel($field, $change['old']),
                $this->getValueLabel($field, $change['new'])
            );
        }

        $activityLog = new ActivityLog();
        $activityLog->setAction('client_updated');
        $activityLog->setEventType('client.updated');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Обновлены данные клиента: %s',
            implode(', ', $descriptions)
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function notifyIfImportant(ClientUpdated $event): void
    {
        $changedFields = $event->getChangedFields();

        // Отправляем уведомление при смене категории на VIP
        if (isset($changedFields['category']) && $changedFields['category']['new'] === 'vip') {
            // Здесь можно отправить уведомление менеджеру
            // Например, через NotificationService
        }

        // Отправляем уведомление при смене сегмента на опт
        if (isset($changedFields['segment']) && $changedFields['segment']['new'] === 'wholesale') {
            // Здесь можно отправить уведомление
        }
    }

    private function getFieldLabel(string $field): string
    {
        return match($field) {
            'segment' => 'Сегмент',
            'category' => 'Категория',
            default => $field,
        };
    }

    private function getValueLabel(string $field, string $value): string
    {
        return match($field) {
            'segment' => $value === 'retail' ? 'Розница' : 'Опт',
            'category' => match($value) {
                'new' => 'Новый',
                'regular' => 'Постоянный',
                'vip' => 'VIP',
                'potential' => 'Потенциальный',
                default => $value,
            },
            default => $value,
        };
    }
}
