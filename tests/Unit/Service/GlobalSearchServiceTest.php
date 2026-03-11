<?php

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Service\GlobalSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class GlobalSearchServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $taskRepository;
    private GlobalSearchService $searchService;

    protected function setUp(): void
    {
        $this->taskRepository = $this->createMock(EntityRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getRepository')
            ->willReturn($this->taskRepository);

        $this->searchService = new GlobalSearchService($this->entityManager);
    }

    public function testSearchWithShortQuery(): void
    {
        $result = $this->searchService->search('a');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Query too short', $result['error']);
    }

    public function testSearchReturnsStructure(): void
    {
        $this->taskRepository->method('createQueryBuilder')->willReturn($this->createMock(\Doctrine\ORM\QueryBuilder::class));

        $result = $this->searchService->search('test');

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('clients', $result);
        $this->assertArrayHasKey('deals', $result);
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('comments', $result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testGetSuggestionsWithShortQuery(): void
    {
        $result = $this->searchService->getSuggestions('a');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSuggestionsReturnsStructure(): void
    {
        $this->taskRepository->method('createQueryBuilder')->willReturn($this->createMock(\Doctrine\ORM\QueryBuilder::class));

        $result = $this->searchService->getSuggestions('test', 5);

        $this->assertIsArray($result);
    }
}
