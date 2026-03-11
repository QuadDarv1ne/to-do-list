<?php

namespace App\Command;

use App\Entity\EmailTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-email-templates',
    description: 'Initialize default email templates',
)]
class InitEmailTemplatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Initializing Email Templates');

        $templates = [
            [
                'code' => 'task_assigned',
                'subject' => 'Вам назначена задача: {{taskTitle}}',
                'bodyHtml' => $this->getTaskAssignedTemplate(),
                'variables' => ['taskTitle', 'taskDescription', 'taskUrl', 'assignedBy'],
            ],
            [
                'code' => 'task_due_soon',
                'subject' => 'Скоро дедлайн: {{taskTitle}}',
                'bodyHtml' => $this->getTaskDueSoonTemplate(),
                'variables' => ['taskTitle', 'dueDate', 'taskUrl'],
            ],
            [
                'code' => 'welcome',
                'subject' => 'Добро пожаловать, {{userName}}!',
                'bodyHtml' => $this->getWelcomeTemplate(),
                'variables' => ['userName', 'loginUrl'],
            ],
            [
                'code' => 'password_reset',
                'subject' => 'Сброс пароля',
                'bodyHtml' => $this->getPasswordResetTemplate(),
                'variables' => ['userName', 'resetUrl'],
            ],
        ];

        $count = 0;
        foreach ($templates as $templateData) {
            $existing = $this->entityManager->getRepository(EmailTemplate::class)
                ->findOneBy(['code' => $templateData['code']]);

            if (!$existing) {
                $template = new EmailTemplate();
                $template->setCode($templateData['code']);
                $template->setSubject($templateData['subject']);
                $template->setBodyHtml($templateData['bodyHtml']);
                $template->setVariables($templateData['variables']);
                
                $this->entityManager->persist($template);
                $count++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created %d email templates', $count));

        return Command::SUCCESS;
    }

    private function getTaskAssignedTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #e0e0e0; }
        .button { display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Новая задача</h1>
        </div>
        <div class="content">
            <p>Вам назначена новая задача:</p>
            <h2>{{taskTitle}}</h2>
            <p>{{taskDescription}}</p>
            <p><strong>Назначил:</strong> {{assignedBy}}</p>
            <p><a href="{{taskUrl}}" class="button">Открыть задачу</a></p>
        </div>
        <div class="footer">
            <p>Это автоматическое уведомление из системы управления задачами</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getTaskDueSoonTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #F59E0B; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #e0e0e0; }
        .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin: 16px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #F59E0B; color: white; text-decoration: none; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Скоро дедлайн</h1>
        </div>
        <div class="content">
            <div class="warning">
                <strong>Внимание!</strong> Срок выполнения задачи истекает скоро.
            </div>
            <h2>{{taskTitle}}</h2>
            <p><strong>Дедлайн:</strong> {{dueDate}}</p>
            <p><a href="{{taskUrl}}" class="button">Открыть задачу</a></p>
        </div>
        <div class="footer">
            <p>Это автоматическое уведомление из системы управления задачами</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getWelcomeTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10B981; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #e0e0e0; }
        .button { display: inline-block; padding: 12px 24px; background: #10B981; color: white; text-decoration: none; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Добро пожаловать!</h1>
        </div>
        <div class="content">
            <p>Здравствуйте, {{userName}}!</p>
            <p>Ваш аккаунт успешно создан. Теперь вы можете пользоваться системой управления задачами.</p>
            <p><a href="{{loginUrl}}" class="button">Войти в систему</a></p>
        </div>
        <div class="footer">
            <p>Это автоматическое уведомление из системы управления задачами</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getPasswordResetTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6366F1; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #e0e0e0; }
        .button { display: inline-block; padding: 12px 24px; background: #6366F1; color: white; text-decoration: none; border-radius: 4px; }
        .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px; margin: 16px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Сброс пароля</h1>
        </div>
        <div class="content">
            <p>Здравствуйте, {{userName}}!</p>
            <p>Вы запросили сброс пароля. Нажмите на кнопку ниже, чтобы установить новый пароль:</p>
            <p><a href="{{resetUrl}}" class="button">Сбросить пароль</a></p>
            <div class="warning">
                <strong>Внимание!</strong> Ссылка действительна в течение 1 часа.
            </div>
            <p>Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.</p>
        </div>
        <div class="footer">
            <p>Это автоматическое уведомление из системы управления задачами</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
