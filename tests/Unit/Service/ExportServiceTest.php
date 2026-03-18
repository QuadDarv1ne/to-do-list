<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\ExportService
 */
class ExportServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $em;
    private SerializerInterface|MockObject $serializer;
    private TaskRepository|MockObject $taskRepo;
    private ExportService $exportService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->taskRepo = $this->createMock(TaskRepository::class);

        $this->exportService = new ExportService(
            $this->em,
            $this->serializer,
            $this->taskRepo,
        );
    }

    public function testExportTasksToCsvReturnsStreamedResponse(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->taskRepo
            ->method('findByUserWithFilters')
            ->willReturn([]);

        // Act
        $response = $this->exportService->exportTasksToCsv($user);

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="tasks_', $response->headers->get('Content-Disposition'));
    }

    public function testExportTasksToExcelReturnsStreamedResponse(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $this->taskRepo
            ->method('findByUserWithFilters')
            ->willReturn([]);

        // Act
        $response = $this->exportService->exportTasksToExcel($user);

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="tasks_', $response->headers->get('Content-Disposition'));
    }

    public function testExportTasksToJsonReturnsStreamedResponse(): void
    {
        // Arrange
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');

        $tasks = [];
        
        $this->taskRepo
            ->method('findByUserWithFilters')
            ->willReturn($tasks);

        $this->serializer
            ->method('serialize')
            ->willReturn('[]');

        // Act
        $response = $this->exportService->exportTasksToJson($user);

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="tasks_', $response->headers->get('Content-Disposition'));
    }

    public function testExportUsersToCsvReturnsStreamedResponse(): void
    {
        // Arrange
        $this->em
            ->method('getRepository')
            ->willReturnCallback(function ($class) {
                $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
                $repo->method('findBy')->willReturn([]);
                return $repo;
            });

        // Act
        $response = $this->exportService->exportUsersToCsv();

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="users_', $response->headers->get('Content-Disposition'));
    }

    public function testExportDealsToExcelReturnsStreamedResponse(): void
    {
        // Arrange
        $this->em
            ->method('getRepository')
            ->willReturnCallback(function ($class) {
                $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
                $repo->method('findBy')->willReturn([]);
                return $repo;
            });

        // Act
        $response = $this->exportService->exportDealsToExcel();

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="deals_', $response->headers->get('Content-Disposition'));
    }

    public function testEscapeCsvRemovesSpecialCharacters(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(ExportService::class);
        $method = $reflection->getMethod('escapeCsv');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('test  ', $method->invoke($this->exportService, "test\r\n"));
        $this->assertEquals('test ', $method->invoke($this->exportService, "test\t"));
        $this->assertEquals('', $method->invoke($this->exportService, null));
    }

    public function testGetStatusTextReturnsTranslatedStatus(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(ExportService::class);
        $method = $reflection->getMethod('getStatusText');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('В ожидании', $method->invoke($this->exportService, 'pending'));
        $this->assertEquals('В работе', $method->invoke($this->exportService, 'in_progress'));
        $this->assertEquals('Завершено', $method->invoke($this->exportService, 'completed'));
        $this->assertEquals('Отменено', $method->invoke($this->exportService, 'cancelled'));
    }

    public function testGetPriorityTextReturnsTranslatedPriority(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(ExportService::class);
        $method = $reflection->getMethod('getPriorityText');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertEquals('Низкий', $method->invoke($this->exportService, 'low'));
        $this->assertEquals('Средний', $method->invoke($this->exportService, 'medium'));
        $this->assertEquals('Высокий', $method->invoke($this->exportService, 'high'));
        $this->assertEquals('Срочно', $method->invoke($this->exportService, 'urgent'));
    }
}
