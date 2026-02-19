<?php

namespace App\Tests\Unit\Service;

use App\Service\InputValidationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class InputValidationServiceTest extends TestCase
{
    private InputValidationService $service;

    protected function setUp(): void
    {
        $this->service = new InputValidationService();
    }

    public function testValidateString(): void
    {
        $this->assertNull($this->service->validateString(null));
        $this->assertNull($this->service->validateString(''));
        $this->assertEquals('test', $this->service->validateString('test'));
        $this->assertEquals('test', $this->service->validateString('  test  '));
        $this->assertEquals('test', $this->service->validateString('<script>test</script>'));

        $longString = str_repeat('a', 300);
        $result = $this->service->validateString($longString, 255);
        $this->assertEquals(255, \strlen($result));
    }

    public function testValidateInt(): void
    {
        $this->assertNull($this->service->validateInt(null));
        $this->assertNull($this->service->validateInt(''));
        $this->assertNull($this->service->validateInt('abc'));
        $this->assertEquals(42, $this->service->validateInt('42'));
        $this->assertEquals(42, $this->service->validateInt(42));
        $this->assertEquals(10, $this->service->validateInt(5, 10));
        $this->assertEquals(100, $this->service->validateInt(150, null, 100));
    }

    public function testValidateBool(): void
    {
        $this->assertTrue($this->service->validateBool(true));
        $this->assertFalse($this->service->validateBool(false));
        $this->assertTrue($this->service->validateBool('1'));
        $this->assertTrue($this->service->validateBool('true'));
        $this->assertFalse($this->service->validateBool('0'));
        $this->assertFalse($this->service->validateBool('false'));
    }

    public function testValidateDate(): void
    {
        $this->assertNull($this->service->validateDate(null));
        $this->assertNull($this->service->validateDate(''));
        $this->assertNull($this->service->validateDate('invalid'));

        $date = $this->service->validateDate('2024-01-15');
        $this->assertInstanceOf(\DateTimeInterface::class, $date);
        $this->assertEquals('2024-01-15', $date->format('Y-m-d'));
    }

    public function testValidateEnum(): void
    {
        $allowed = ['pending', 'in_progress', 'completed'];

        $this->assertNull($this->service->validateEnum(null, $allowed));
        $this->assertNull($this->service->validateEnum('', $allowed));
        $this->assertNull($this->service->validateEnum('invalid', $allowed));
        $this->assertEquals('pending', $this->service->validateEnum('pending', $allowed));
    }

    public function testValidateIntArray(): void
    {
        $this->assertEquals([], $this->service->validateIntArray('not-array'));
        $this->assertEquals([], $this->service->validateIntArray([]));
        $this->assertEquals([1, 2, 3], $this->service->validateIntArray([1, 2, 3]));
        $this->assertEquals([0 => 1, 2 => 2], $this->service->validateIntArray([1, 'invalid', 2]));
    }

    public function testSanitizeTableName(): void
    {
        $this->assertEquals('users', $this->service->sanitizeTableName('users'));
        $this->assertEquals('task_categories', $this->service->sanitizeTableName('task_categories'));
        $this->assertEquals('usersDROPTABLEusers', $this->service->sanitizeTableName('users; DROP TABLE users--'));
        // Test for empty string when SQL injection attempt is detected
        $this->assertEquals('', $this->service->sanitizeTableName(''));
    }

    public function testValidatePagination(): void
    {
        $request = new Request(['page' => '2', 'limit' => '20']);
        $result = $this->service->validatePagination($request);

        $this->assertEquals(2, $result['page']);
        $this->assertEquals(20, $result['limit']);
        $this->assertEquals(20, $result['offset']);
    }

    public function testValidateSort(): void
    {
        $allowedFields = ['id', 'name', 'created_at'];
        $request = new Request(['sort' => 'name', 'direction' => 'asc']);
        $result = $this->service->validateSort($request, $allowedFields);

        $this->assertEquals('name', $result['sort']);
        $this->assertEquals('ASC', $result['direction']);

        // Test invalid field
        $request = new Request(['sort' => 'invalid']);
        $result = $this->service->validateSort($request, $allowedFields);
        $this->assertEquals('id', $result['sort']);
    }
}
