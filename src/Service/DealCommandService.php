<?php

namespace App\Service;

use App\Domain\Deal\Event\DealCreated;
use App\Domain\Deal\Event\DealLost;
use App\Domain\Deal\Event\DealStageChanged;
use App\Domain\Deal\Event\DealWon;
use App\DTO\CreateDealDTO;
use App\DTO\UpdateDealDTO;
use App\Entity\Deal;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Сервис для управления сделками с использованием DTO
 */
final class DealCommandService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DealRepository $dealRepository,
        private ClientRepository $clientRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Создать сделку
     */
    public function createDeal(CreateDealDTO $dto, User $manager): Deal
    {
        $client = $this->clientRepository->find($dto->getClientId());

        if (!$client) {
            throw new \InvalidArgumentException(sprintf('Client with id %d not found', $dto->getClientId()));
        }

        $deal = new Deal();
        $deal->setTitle($dto->getTitle());
        $deal->setClient($client);
        $deal->setManager($manager);
        $deal->setAmount($dto->getAmount());
        $deal->setStage($dto->getStage());
        $deal->setDescription($dto->getDescription());

        $expectedCloseDate = $dto->getExpectedCloseDateAsDateTime();
        if ($expectedCloseDate) {
            $deal->setExpectedCloseDate($expectedCloseDate);
        }

        $this->entityManager->persist($deal);
        $this->entityManager->flush(); // Сначала flush, чтобы получить ID

        // Отправляем Domain Event (после flush, когда ID уже присвоен)
        $this->dispatchDealCreated($deal, $manager);

        return $deal;
    }

    /**
     * Обновить сделку
     */
    public function updateDeal(UpdateDealDTO $dto, User $updater): Deal
    {
        $deal = $this->dealRepository->find($dto->getId());

        if (!$deal) {
            throw new \InvalidArgumentException(sprintf('Deal with id %d not found', $dto->getId()));
        }

        // Сохраняем старые значения для Domain Events
        $oldStatus = $deal->getStatus();
        $oldStage = $deal->getStage();

        // Обновляем поля из DTO
        if ($dto->getTitle() !== null) {
            $deal->setTitle($dto->getTitle());
        }

        if ($dto->getAmount() !== null) {
            $deal->setAmount($dto->getAmount());
        }

        if ($dto->getStage() !== null) {
            $deal->setStage($dto->getStage());
        }

        if ($dto->getStatus() !== null) {
            $deal->setStatus($dto->getStatus());

            // Устанавливаем дату закрытия при смене статуса на won/lost
            if (in_array($dto->getStatus(), ['won', 'lost'])) {
                $deal->setActualCloseDate(new \DateTime());
            }

            // Устанавливаем причину отмены
            if ($dto->getStatus() === 'lost' && $dto->getLostReason() !== null) {
                $deal->setLostReason($dto->getLostReason());
            }
        }

        if ($dto->getDescription() !== null) {
            $deal->setDescription($dto->getDescription());
        }

        if ($dto->getExpectedCloseDate() !== null) {
            $expectedCloseDate = $dto->getExpectedCloseDateAsDateTime();
            if ($expectedCloseDate) {
                $deal->setExpectedCloseDate($expectedCloseDate);
            }
        }

        $deal->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Отправляем Domain Events
        $this->dispatchDealEvents($deal, $updater, $oldStatus, $oldStage);

        return $deal;
    }

    /**
     * Выиграть сделку
     */
    public function winDeal(Deal $deal, User $updater): Deal
    {
        $deal->setStatus('won');
        $deal->setActualCloseDate(new \DateTime());
        $deal->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Отправляем Domain Event
        $this->dispatchDealWon($deal, $updater);

        return $deal;
    }

    /**
     * Отклонить сделку
     */
    public function loseDeal(Deal $deal, User $updater, string $reason): Deal
    {
        $deal->setStatus('lost');
        $deal->setLostReason($reason);
        $deal->setActualCloseDate(new \DateTime());
        $deal->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Отправляем Domain Event
        $this->dispatchDealLost($deal, $updater, $reason);

        return $deal;
    }

    /**
     * Отправить Domain Event о создании сделки
     */
    private function dispatchDealCreated(Deal $deal, User $manager): void
    {
        $event = DealCreated::create(
            $deal->getId(),
            $deal->getTitle(),
            $deal->getAmount(),
            $manager->getId(),
            $deal->getClient()->getId(),
            $deal->getStage(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Events об изменениях сделки
     */
    private function dispatchDealEvents(Deal $deal, User $updater, string $oldStatus, string $oldStage): void
    {
        // Event изменения статуса
        if ($deal->getStatus() !== $oldStatus) {
            if ($deal->getStatus() === 'won') {
                $this->dispatchDealWon($deal, $updater);
            } elseif ($deal->getStatus() === 'lost') {
                $this->dispatchDealLost($deal, $updater, $deal->getLostReason() ?? 'Не указано');
            }
        }

        // Event изменения этапа
        if ($deal->getStage() !== $oldStage) {
            $event = DealStageChanged::create(
                $deal->getId(),
                $deal->getTitle(),
                $oldStage,
                $deal->getStage(),
                $updater->getId(),
            );

            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * Отправить Domain Event о выигрыше сделки
     */
    private function dispatchDealWon(Deal $deal, User $updater): void
    {
        $event = DealWon::create(
            $deal->getId(),
            $deal->getTitle(),
            $deal->getAmount(),
            $updater->getId(),
            $deal->getActualCloseDate() ?? new \DateTimeImmutable(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Event об отклонении сделки
     */
    private function dispatchDealLost(Deal $deal, User $updater, string $reason): void
    {
        $event = DealLost::create(
            $deal->getId(),
            $deal->getTitle(),
            $reason,
            $updater->getId(),
            $deal->getActualCloseDate() ?? new \DateTimeImmutable(),
        );

        $this->eventDispatcher->dispatch($event);
    }
}
