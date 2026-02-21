<?php

namespace App\Service;

use App\Domain\Client\Event\ClientCreated;
use App\Domain\Client\Event\ClientUpdated;
use App\DTO\CreateClientDTO;
use App\DTO\UpdateClientDTO;
use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Сервис для управления клиентами с использованием DTO
 */
final class ClientCommandService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Создать клиента
     */
    public function createClient(CreateClientDTO $dto, User $manager): Client
    {
        $client = new Client();
        $client->setCompanyName($dto->getCompanyName());
        $client->setInn($dto->getInn());
        $client->setKpp($dto->getKpp());
        $client->setContactPerson($dto->getContactPerson());
        $client->setPhone($dto->getPhone());
        $client->setEmail($dto->getEmail());
        $client->setAddress($dto->getAddress());
        $client->setSegment($dto->getSegment());
        $client->setCategory($dto->getCategory());
        $client->setNotes($dto->getNotes());
        $client->setManager($manager);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        // Отправляем Domain Event (после flush, когда ID уже присвоен)
        $this->dispatchClientCreated($client, $manager);

        return $client;
    }

    /**
     * Обновить клиента
     */
    public function updateClient(UpdateClientDTO $dto, User $updater): Client
    {
        $client = $this->clientRepository->find($dto->getId());

        if (!$client) {
            throw new \InvalidArgumentException(sprintf('Client with id %d not found', $dto->getId()));
        }

        // Сохраняем старые значения для Domain Events
        $oldSegment = $client->getSegment();
        $oldCategory = $client->getCategory();

        // Обновляем поля из DTO
        if ($dto->getCompanyName() !== null) {
            $client->setCompanyName($dto->getCompanyName());
        }

        if ($dto->getInn() !== null) {
            $client->setInn($dto->getInn());
        }

        if ($dto->getKpp() !== null) {
            $client->setKpp($dto->getKpp());
        }

        if ($dto->getContactPerson() !== null) {
            $client->setContactPerson($dto->getContactPerson());
        }

        if ($dto->getPhone() !== null) {
            $client->setPhone($dto->getPhone());
        }

        if ($dto->getEmail() !== null) {
            $client->setEmail($dto->getEmail());
        }

        if ($dto->getAddress() !== null) {
            $client->setAddress($dto->getAddress());
        }

        if ($dto->getSegment() !== null) {
            $client->setSegment($dto->getSegment());
        }

        if ($dto->getCategory() !== null) {
            $client->setCategory($dto->getCategory());
        }

        if ($dto->getNotes() !== null) {
            $client->setNotes($dto->getNotes());
        }

        $client->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Отправляем Domain Events
        $this->dispatchClientEvents($client, $updater, $oldSegment, $oldCategory);

        return $client;
    }

    /**
     * Отправить Domain Event о создании клиента
     */
    private function dispatchClientCreated(Client $client, User $manager): void
    {
        $event = ClientCreated::create(
            $client->getId(),
            $client->getCompanyName(),
            $client->getEmail() ?? '',
            $client->getPhone(),
            $manager->getId(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Events об изменениях клиента
     */
    private function dispatchClientEvents(Client $client, User $updater, string $oldSegment, string $oldCategory): void
    {
        $changedFields = [];

        // Собираем изменённые поля
        if ($client->getSegment() !== $oldSegment) {
            $changedFields['segment'] = ['old' => $oldSegment, 'new' => $client->getSegment()];
        }

        if ($client->getCategory() !== $oldCategory) {
            $changedFields['category'] = ['old' => $oldCategory, 'new' => $client->getCategory()];
        }

        // Отправляем событие только если есть изменения
        if (!empty($changedFields)) {
            $event = ClientUpdated::create(
                $client->getId(),
                $changedFields,
                $updater->getId(),
            );

            $this->eventDispatcher->dispatch($event);
        }
    }
}
