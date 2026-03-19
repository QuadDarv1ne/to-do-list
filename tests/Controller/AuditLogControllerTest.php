<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @covers \App\Controller\AuditLogController
 */
class AuditLogControllerTest extends WebTestCase
{
    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        $this->adminUser = (new User())
            ->setEmail('admin@example.com')
            ->setFullName('Admin User')
            ->setPassword('hashed_password')
            ->setRoles(['ROLE_ADMIN']);

        $this->regularUser = (new User())
            ->setEmail('user@example.com')
            ->setFullName('Regular User')
            ->setPassword('hashed_password')
            ->setRoles(['ROLE_USER']);
    }

    public function testIndexAccessDeniedForRegularUser(): void
    {
        $client = static::createClient();
        
        // Создаём тестовую сессию с обычным пользователем
        $client->loginUser($this->regularUser);

        $client->request('GET', '/admin/audit');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/audit');

        $this->assertResponseRedirects('/login');
    }

    public function testApiAccessDeniedForRegularUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->regularUser);

        $client->request('GET', '/admin/audit/api');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testStatisticsAccessDeniedForRegularUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->regularUser);

        $client->request('GET', '/admin/audit/statistics');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testViewAccessDeniedForRegularUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->regularUser);

        $client->request('GET', '/admin/audit/1');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testApiReturnsJsonForAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);

        $client->request('GET', '/admin/audit/api');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertTrue($response['success']);
    }

    public function testApiPagination(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);

        $client->request('GET', '/admin/audit/api?page=1&limit=10');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('page', $response['meta']);
        $this->assertArrayHasKey('limit', $response['meta']);
        $this->assertArrayHasKey('pages', $response['meta']);
        $this->assertEquals(1, $response['meta']['page']);
        $this->assertEquals(10, $response['meta']['limit']);
    }

    public function testApiFilterByAction(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);

        $client->request('GET', '/admin/audit/api?action=update');

        $this->assertResponseIsSuccessful();
    }

    public function testApiFilterByEntity(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);

        $client->request('GET', '/admin/audit/api?entity=Task');

        $this->assertResponseIsSuccessful();
    }

    public function testStatisticsReturnsData(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);

        $client->request('GET', '/admin/audit/statistics');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertTrue($response['success']);
        
        $this->assertArrayHasKey('by_action', $response['data']);
        $this->assertArrayHasKey('by_entity', $response['data']);
        $this->assertArrayHasKey('daily_activity', $response['data']);
        $this->assertArrayHasKey('top_users', $response['data']);
    }
}
