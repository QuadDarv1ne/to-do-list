<?php

namespace App\Tests\Unit\Entity;

use App\Entity\EmailTemplate;
use PHPUnit\Framework\TestCase;

class EmailTemplateTest extends TestCase
{
    public function testCreateEmailTemplate(): void
    {
        $template = new EmailTemplate();
        $template->setCode('test_template');
        $template->setSubject('Test Subject');
        $template->setBodyHtml('<p>Test Body</p>');
        $template->setVariables(['name', 'email']);

        $this->assertEquals('test_template', $template->getCode());
        $this->assertEquals('Test Subject', $template->getSubject());
        $this->assertEquals('<p>Test Body</p>', $template->getBodyHtml());
        $this->assertEquals(['name', 'email'], $template->getVariables());
        $this->assertTrue($template->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $template->getCreatedAt());
    }

    public function testRenderTemplate(): void
    {
        $template = new EmailTemplate();
        $template->setCode('welcome');
        $template->setSubject('Hello {{name}}!');
        $template->setBodyHtml('<p>Welcome {{name}}, your email is {{email}}</p>');

        $rendered = $template->render([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('Hello John!', $rendered['subject']);
        $this->assertEquals('<p>Welcome John, your email is john@example.com</p>', $rendered['bodyHtml']);
    }

    public function testRenderTemplateWithTextBody(): void
    {
        $template = new EmailTemplate();
        $template->setCode('test');
        $template->setSubject('Test {{name}}');
        $template->setBodyHtml('<p>HTML {{name}}</p>');
        $template->setBodyText('Text {{name}}');

        $rendered = $template->render(['name' => 'Test']);

        $this->assertEquals('Text Test', $rendered['bodyText']);
    }

    public function testRenderTemplateWithoutTextBody(): void
    {
        $template = new EmailTemplate();
        $template->setCode('test');
        $template->setSubject('Test');
        $template->setBodyHtml('<p>HTML Content</p>');

        $rendered = $template->render([]);

        $this->assertEquals('HTML Content', $rendered['bodyText']);
    }

    public function testIsActiveCanBeChanged(): void
    {
        $template = new EmailTemplate();
        $this->assertTrue($template->isActive());

        $template->setIsActive(false);
        $this->assertFalse($template->isActive());
    }
}
