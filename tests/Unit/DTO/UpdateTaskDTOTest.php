<?php

namespace App\Tests\Unit\DTO;

use App\DTO\UpdateTaskDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

class UpdateTaskDTOTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $request = new Request();
        $request->request = new InputBag([
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => 'completed',
            'priority' => 'urgent',
            'assignedUserId' => 10,
            'categoryId' => 5,
            'dueDate' => '2026-05-01',
            'progress' => 75,
            'tags' => [3, 4, 5],
            'notify' => false,
        ]);

        $dto = UpdateTaskDTO::fromRequest($request, 42);

        $this->assertEquals(42, $dto->getId());
        $this->assertEquals('Updated Title', $dto->getTitle());
        $this->assertEquals('Updated Description', $dto->getDescription());
        $this->assertEquals('completed', $dto->getStatus());
        $this->assertEquals('urgent', $dto->getPriority());
        $this->assertEquals(10, $dto->getAssignedUserId());
        $this->assertEquals(75, $dto->getProgress());
        $this->assertFalse($dto->shouldNotify());
    }

    public function testCreateFromArray(): void
    {
        $data = [
            'id' => 100,
            'title' => 'Array Update',
            'priority' => 'low',
            'progress' => 50,
        ];

        $dto = UpdateTaskDTO::fromArray($data);

        $this->assertEquals(100, $dto->getId());
        $this->assertEquals('Array Update', $dto->getTitle());
        $this->assertEquals('low', $dto->getPriority());
        $this->assertEquals(50, $dto->getProgress());
    }

    public function testPartialUpdate(): void
    {
        $dto = UpdateTaskDTO::fromArray([
            'id' => 1,
            'title' => 'Only Title Updated',
        ]);

        $this->assertEquals('Only Title Updated', $dto->getTitle());
        $this->assertNull($dto->getDescription());
        $this->assertNull($dto->getStatus());
        $this->assertNull($dto->getPriority());
    }

    public function testHasChanges(): void
    {
        // С изменениями
        $dtoWithChanges = UpdateTaskDTO::fromArray([
            'id' => 1,
            'title' => 'Changed',
        ]);
        $this->assertTrue($dtoWithChanges->hasChanges());

        // Без изменений (только ID)
        $dtoNoChanges = UpdateTaskDTO::fromArray(['id' => 1]);
        $this->assertFalse($dtoNoChanges->hasChanges());
    }

    public function testGetDueDateAsDateTime(): void
    {
        $dto = UpdateTaskDTO::fromArray([
            'id' => 1,
            'dueDate' => '2026-06-15T14:30:00+03:00',
        ]);

        $dateTime = $dto->getDueDateAsDateTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $dateTime);
    }

    public function testGetDueDateAsDateTimeWithNull(): void
    {
        $dto = UpdateTaskDTO::fromArray(['id' => 1]);
        $this->assertNull($dto->getDueDateAsDateTime());
    }

    public function testToArray(): void
    {
        $dto = UpdateTaskDTO::fromArray([
            'id' => 1,
            'title' => 'Test',
            'status' => 'in_progress',
            'progress' => 25,
            'tags' => [1, 2],
            'notify' => true,
        ]);

        $array = $dto->toArray();

        $this->assertEquals('Test', $array['title']);
        $this->assertEquals('in_progress', $array['status']);
        $this->assertEquals(25, $array['progress']);
        $this->assertEquals([1, 2], $array['tags']);
        $this->assertTrue($array['notify']);
    }

    public function testDefaultNotifyValue(): void
    {
        $dto = UpdateTaskDTO::fromArray(['id' => 1]);
        $this->assertTrue($dto->shouldNotify());
    }

    public function testTagIdsDefaultToArray(): void
    {
        $dto = UpdateTaskDTO::fromArray(['id' => 1]);
        $this->assertEquals([], $dto->getTagIds());
    }
}
