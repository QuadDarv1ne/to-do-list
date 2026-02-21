<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateDealDTO;
use App\DTO\UpdateDealDTO;
use App\Entity\Client;
use App\Entity\Deal;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use App\Service\DealCommandService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DealCommandServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private DealRepository|MockObject $dealRepository;
    private ClientRepository|MockObject $clientRepository;
    private EventDispatcherInterface|MockObject $eventDispatcher;
    private DealCommandService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->dealRepository = $this->createMock(DealRepository::class);
        $this->clientRepository = $this->createMock(ClientRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // Event Dispatcher не вызывает реальные события в тестах
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->service = new DealCommandService(
            $this->entityManager,
            $this->dealRepository,
            $this->clientRepository,
            $this->eventDispatcher
        );
    }

    public function testCreateDealSuccessfully(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);
        
        $manager = $this->createMock(User::class);
        $manager->method('getId')->willReturn(1);

        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getTitle')->willReturn('Test Deal');
        $deal->method('getAmount')->willReturn('10000.00');
        $deal->method('getStage')->willReturn('lead');
        $deal->method('getClient')->willReturn($client);

        $dto = $this->createMockCreateDealDTO([
            'clientId' => 1,
            'title' => 'Test Deal',
            'amount' => '10000.00',
            'stage' => 'lead',
        ]);

        $this->clientRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($client);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Deal::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function() use ($deal) {
                // После flush Deal получает ID
                $deal->method('getId')->willReturn(1);
            });

        $this->eventDispatcher
            ->expects($this->any())
            ->method('dispatch')
            ->willReturnArgument(0);

        $result = $this->service->createDeal($dto, $manager);

        $this->assertInstanceOf(Deal::class, $result);
    }

    public function testCreateDealWithNonExistentClient(): void
    {
        $manager = new User();

        $this->clientRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $dto = $this->createMockCreateDealDTO([
            'clientId' => 999,
            'title' => 'Test Deal',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client with id 999 not found');

        $this->service->createDeal($dto, $manager);
    }

    public function testUpdateDealSuccessfully(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getTitle')->willReturn('Old Title');
        $deal->method('getAmount')->willReturn('5000.00');
        $deal->method('getStage')->willReturn('lead');
        $deal->method('getStatus')->willReturn('in_progress');
        $deal->expects($this->any())->method('setTitle')->with('New Title');
        $deal->expects($this->any())->method('setAmount')->with('10000.00');

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO([
            'id' => 1,
            'title' => 'New Title',
            'amount' => '10000.00',
        ]);

        $updatedDeal = $this->service->updateDeal($dto, $updater);

        $this->assertInstanceOf(Deal::class, $updatedDeal);
    }

    public function testUpdateDealWithNonExistentDeal(): void
    {
        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $dto = $this->createMockUpdateDealDTO(['id' => 999]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Deal with id 999 not found');

        $this->service->updateDeal($dto, $updater);
    }

    public function testWinDeal(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getStatus')->willReturn('in_progress');
        $deal->method('getTitle')->willReturn('Test Deal');
        $deal->method('getAmount')->willReturn('10000.00');
        $deal->method('getActualCloseDate')->willReturn(null);
        $deal->expects($this->once())->method('setStatus')->with('won');
        $deal->expects($this->once())->method('setActualCloseDate')->with($this->isInstanceOf(\DateTime::class));
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = $this->createMock(User::class);
        $updater->method('getId')->willReturn(1);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->eventDispatcher
            ->expects($this->any())
            ->method('dispatch')
            ->willReturnArgument(0);

        $updatedDeal = $this->service->winDeal($deal, $updater);

        $this->assertInstanceOf(Deal::class, $updatedDeal);
    }

    public function testLoseDeal(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getStatus')->willReturn('in_progress');
        $deal->method('getTitle')->willReturn('Test Deal');
        $deal->method('getActualCloseDate')->willReturn(null);
        $deal->expects($this->once())->method('setStatus')->with('lost');
        $deal->expects($this->once())->method('setLostReason')->with('Цена слишком высокая');
        $deal->expects($this->once())->method('setActualCloseDate')->with($this->isInstanceOf(\DateTime::class));
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = $this->createMock(User::class);
        $updater->method('getId')->willReturn(1);
        
        $reason = 'Цена слишком высокая';

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->eventDispatcher
            ->expects($this->any())
            ->method('dispatch')
            ->willReturnArgument(0);

        $updatedDeal = $this->service->loseDeal($deal, $updater, $reason);

        $this->assertInstanceOf(Deal::class, $updatedDeal);
    }

    public function testUpdateDealSetsActualCloseDateOnWon(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getStatus')->willReturn('in_progress');
        $deal->expects($this->once())->method('setStatus')->with('won');
        $deal->expects($this->once())->method('setActualCloseDate')->with($this->isInstanceOf(\DateTime::class));
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO([
            'id' => 1,
            'status' => 'won',
        ]);

        $this->service->updateDeal($dto, $updater);
    }

    public function testUpdateDealSetsLostReason(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->method('getStatus')->willReturn('in_progress');
        $deal->expects($this->once())->method('setStatus')->with('lost');
        $deal->expects($this->once())->method('setLostReason')->with('Конкурент предложил лучше');
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO([
            'id' => 1,
            'status' => 'lost',
            'lostReason' => 'Конкурент предложил лучше',
        ]);

        $this->service->updateDeal($dto, $updater);
    }

    public function testUpdateDealWithExpectedCloseDate(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->expects($this->once())->method('setExpectedCloseDate')->with($this->isInstanceOf(\DateTimeImmutable::class));
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO([
            'id' => 1,
            'expectedCloseDate' => '2026-03-15',
        ]);

        $this->service->updateDeal($dto, $updater);
    }

    public function testUpdateDealWithDescription(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->expects($this->once())->method('setDescription')->with('Новое описание сделки');
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO([
            'id' => 1,
            'description' => 'Новое описание сделки',
        ]);

        $this->service->updateDeal($dto, $updater);
    }

    public function testUpdateDealSetsUpdatedAt(): void
    {
        $deal = $this->createMock(Deal::class);
        $deal->method('getId')->willReturn(1);
        $deal->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        $updater = new User();

        $this->dealRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($deal);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $dto = $this->createMockUpdateDealDTO(['id' => 1]);

        $this->service->updateDeal($dto, $updater);
    }

    private function createMockCreateDealDTO(array $data): CreateDealDTO
    {
        $dto = new \ReflectionClass(CreateDealDTO::class);
        $instance = $dto->newInstanceWithoutConstructor();
        
        $reflection = new \ReflectionClass($instance);
        $properties = array_merge([
            'clientId' => 1,
            'title' => 'Test',
            'amount' => '0.00',
            'stage' => 'lead',
            'description' => null,
            'expectedCloseDate' => null,
        ], $data);
        
        foreach ($properties as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($instance, $value);
        }
        
        return $instance;
    }

    private function createMockUpdateDealDTO(array $data): UpdateDealDTO
    {
        $dto = new \ReflectionClass(UpdateDealDTO::class);
        $instance = $dto->newInstanceWithoutConstructor();
        
        $reflection = new \ReflectionClass($instance);
        $properties = array_merge([
            'id' => 1,
            'title' => null,
            'amount' => null,
            'stage' => null,
            'status' => null,
            'description' => null,
            'expectedCloseDate' => null,
            'lostReason' => null,
        ], $data);
        
        foreach ($properties as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($instance, $value);
        }
        
        return $instance;
    }
}
