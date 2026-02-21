<?php

namespace App\Tests\Unit\DTO;

use App\DTO\CreateTaskDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

class CreateTaskDTOTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $request = new Request();
        $request->request = new InputBag([
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'pending',
            'priority' => 'high',
            'assignedUserId' => 5,
            'categoryId' => 3,
            'dueDate' => '2026-03-01T10:00:00+00:00',
            'parentId' => 10,
            'tags' => [1, 2, 3],
        ]);

        $dto = CreateTaskDTO::fromRequest($request);

        $this->assertEquals('Test Task', $dto->getTitle());
        $this->assertEquals('Test Description', $dto->getDescription());
        $this->assertEquals('pending', $dto->getStatus());
        $this->assertEquals('high', $dto->getPriority());
        $this->assertEquals(5, $dto->getAssignedUserId());
        $this->assertEquals(3, $dto->getCategoryId());
        $this->assertEquals('2026-03-01T10:00:00+00:00', $dto->getDueDate());
        $this->assertEquals(10, $dto->getParentId());
        $this->assertEquals([1, 2, 3], $dto->getTagIds());
    }

    public function testCreateFromArray(): void
    {
        $data = [
            'title' => 'Array Task',
            'description' => 'From Array',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'assignedUserId' => 7,
            'categoryId' => 2,
            'dueDate' => '2026-04-15',
            'tags' => [4, 5],
        ];

        $dto = CreateTaskDTO::fromArray($data);

        $this->assertEquals('Array Task', $dto->getTitle());
        $this->assertEquals('From Array', $dto->getDescription());
        $this->assertEquals('in_progress', $dto->getStatus());
        $this->assertEquals('urgent', $dto->getPriority());
    }

    public function testTitleTrimming(): void
    {
        $dto = CreateTaskDTO::fromArray(['title' => '  Trimmed Title  ']);
        $this->assertEquals('Trimmed Title', $dto->getTitle());
    }

    public function testGetDueDateAsDateTime(): void
    {
        $dto = CreateTaskDTO::fromArray([
            'title' => 'Test',
            'dueDate' => '2026-03-01',
        ]);

        $dateTime = $dto->getDueDateAsDateTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $dateTime);
        $this->assertEquals('2026-03-01', $dateTime->format('Y-m-d'));
    }

    public function testGetDueDateAsDateTimeWithInvalidDate(): void
    {
        $dto = CreateTaskDTO::fromArray([
            'title' => 'Test',
            'dueDate' => 'invalid-date',
        ]);

        $this->assertNull($dto->getDueDateAsDateTime());
    }

    public function testToArray(): void
    {
        $dto = CreateTaskDTO::fromArray([
            'title' => 'Test Task',
            'description' => 'Description',
            'priority' => 'medium',
            'tags' => [1, 2],
        ]);

        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Test Task', $array['title']);
        $this->assertEquals('Description', $array['description']);
        $this->assertEquals('medium', $array['priority']);
        $this->assertEquals([1, 2], $array['tags']);
        $this->assertArrayHasKey('dueDate', $array);
    }

    public function testEmptyTitle(): void
    {
        $dto = CreateTaskDTO::fromArray(['title' => '']);
        $this->assertEquals('', $dto->getTitle());
    }

    public function testDefaultValues(): void
    {
        $dto = CreateTaskDTO::fromArray(['title' => 'Test']);

        $this->assertEquals('pending', $dto->getStatus());
        $this->assertEquals('medium', $dto->getPriority());
        $this->assertNull($dto->getAssignedUserId());
        $this->assertNull($dto->getCategoryId());
        $this->assertNull($dto->getDueDate());
        $this->assertEquals([], $dto->getTagIds());
    }
}
