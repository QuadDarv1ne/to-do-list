<?php

namespace App\Infrastructure\EventStore;

use App\Domain\Task\Event\DomainEventInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final readonly class EventStore
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {
    }

    public function append(DomainEventInterface $event): void
    {
        $sql = 'INSERT INTO event_store (event_name, event_data, occurred_at) VALUES (?, ?, ?)';
        
        $this->connection->executeStatement($sql, [
            $event->getEventName(),
            json_encode($event->toArray()),
            $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);

        $this->logger->info('Event stored', [
            'event_name' => $event->getEventName(),
            'occurred_at' => $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function getEvents(?string $eventName = null, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('event_store')
            ->orderBy('occurred_at', 'DESC');

        if ($eventName) {
            $qb->andWhere('event_name = :event_name')
               ->setParameter('event_name', $eventName);
        }

        if ($since) {
            $qb->andWhere('occurred_at >= :since')
               ->setParameter('since', $since->format('Y-m-d H:i:s'));
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
