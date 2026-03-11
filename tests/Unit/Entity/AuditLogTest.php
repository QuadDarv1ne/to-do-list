<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AuditLog;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AuditLogTest extends TestCase
{
    public function testCreateAuditLog(): void
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass('App\Entity\Task');
        $auditLog->setEntityId('123');
        $auditLog->setAction('CREATE');

        $this->assertEquals('App\Entity\Task', $auditLog->getEntityClass());
        $this->assertEquals('123', $auditLog->getEntityId());
        $this->assertEquals('CREATE', $auditLog->getAction());
        $this->assertEquals('Task', $auditLog->getEntityName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $auditLog->getCreatedAt());
    }

    public function testAuditLogWithChanges(): void
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityClass('App\Entity\Task');
        $auditLog->setEntityId('1');
        $auditLog->setAction('UPDATE');
        $auditLog->setChanges([
            'title' => ['old' => 'Old Title', 'new' => 'New Title'],
            'status' => ['old' => 'pending', 'new' => 'in_progress'],
        ]);
        $auditLog->setOldValues(['title' => 'Old Title', 'status' => 'pending']);
        $auditLog->setNewValues(['title' => 'New Title', 'status' => 'in_progress']);

        $this->assertCount(2, $auditLog->getChanges());
        $this->assertEquals('New Title', $auditLog->getNewValues()['title']);
        $this->assertEquals('pending', $auditLog->getOldValues()['status']);
    }

    public function testAuditLogWithUser(): void
    {
        $auditLog = new AuditLog();
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $auditLog->setUser($user);
        $auditLog->setUserName('testuser');
        $auditLog->setUserEmail('test@example.com');

        $this->assertSame($user, $auditLog->getUser());
        $this->assertEquals('testuser', $auditLog->getUserName());
        $this->assertEquals('test@example.com', $auditLog->getUserEmail());
    }

    public function testAuditLogWithRequestInfo(): void
    {
        $auditLog = new AuditLog();
        $auditLog->setIpAddress('192.168.1.1');
        $auditLog->setUserAgent('Mozilla/5.0');
        $auditLog->setReason('Manual update');

        $this->assertEquals('192.168.1.1', $auditLog->getIpAddress());
        $this->assertEquals('Mozilla/5.0', $auditLog->getUserAgent());
        $this->assertEquals('Manual update', $auditLog->getReason());
    }

    public function testEntityNameExtraction(): void
    {
        $auditLog = new AuditLog();
        
        $auditLog->setEntityClass('App\Entity\Task');
        $this->assertEquals('Task', $auditLog->getEntityName());
        
        $auditLog->setEntityClass('App\Entity\User');
        $this->assertEquals('User', $auditLog->getEntityName());
        
        $auditLog->setEntityClass('Very\Long\Namespace\Path\Deal');
        $this->assertEquals('Deal', $auditLog->getEntityName());
    }
}
