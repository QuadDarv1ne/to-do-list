<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:send-daily-digest',
    description: 'Отправляет ежедневный дайджест задач пользователям'
)]
class SendDailyDigestCommand extends Command
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findActiveUsers();

        foreach ($users as $user) {
            try {
                $this->sendDailyDigest($user);
                $io->writeln(sprintf('Дайджест отправлен пользователю: %s (%s)', $user->getFullName(), $user->getEmail()));
            } catch (\Exception $e) {
                $io->error(sprintf('Ошибка при отправке дайджеста пользователю %s: %s', $user->getFullName(), $e->getMessage()));
            }
        }

        $io->success('Ежедневные дайджесты отправлены.');

        return Command::SUCCESS;
    }

    private function sendDailyDigest(User $user): void
    {
        // Получаем задачи пользователя за последний день
        $fromDate = new \DateTime('-1 day');
        $toDate = new \DateTime();

        $pendingTasks = $this->taskRepository->findByUserAndStatus($user, 'pending', $fromDate, $toDate);
        $completedTasks = $this->taskRepository->findByUserAndStatus($user, 'completed', $fromDate, $toDate);

        // Логика отправки email будет реализована позже
        // Для демонстрации просто выводим информацию
        echo sprintf("Дайджест для %s: %d новых задач, %d завершенных\n", 
            $user->getFullName(), 
            count($pendingTasks), 
            count($completedTasks)
        );
    }
}
