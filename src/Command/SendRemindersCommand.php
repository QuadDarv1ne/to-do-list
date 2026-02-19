<?php

namespace App\Command;

use App\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Send due task reminders to users',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private ReminderService $reminderService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sending Task Reminders');

        try {
            $sentCount = $this->reminderService->sendDueReminders();

            if ($sentCount > 0) {
                $io->success(\sprintf('Successfully sent %d reminder(s)', $sentCount));
            } else {
                $io->info('No reminders to send at this time');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error sending reminders: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
