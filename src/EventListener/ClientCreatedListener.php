<?php

namespace App\EventListener;

use App\Domain\Client\Event\ClientCreated;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Слушатель события ClientCreated
 * 
 * Обрабатывает создание клиента:
 * - Записывает запись в Activity Log
 * - Отправляет приветственное письмо (опционально)
 */
#[AsEventListener(event: ClientCreated::class, method: 'onClientCreated')]
final class ClientCreatedListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onClientCreated(ClientCreated $event): void
    {
        // Создаем запись в Activity Log
        $this->logActivity($event);

        // Отправляем приветственное письмо (опционально)
        $this->sendWelcomeEmail($event);
    }

    private function logActivity(ClientCreated $event): void
    {
        $activityLog = new ActivityLog();
        $activityLog->setAction('client_created');
        $activityLog->setEventType('client.created');
        $activityLog->setCreatedAt(new \DateTimeImmutable());
        $activityLog->setDescription(sprintf(
            'Создан клиент "%s" (Email: %s, Телефон: %s)',
            $event->getName(),
            $event->getEmail() ?: 'не указан',
            $event->getPhone() ?: 'не указан'
        ));

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }

    private function sendWelcomeEmail(ClientCreated $event): void
    {
        // Здесь можно отправить приветственное письмо клиенту
        // Например, через NotificationService
        // Пока оставляем заглушку для будущей реализации
    }
}
