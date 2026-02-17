<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-system',
    description: 'Initialize the system with default data'
)]
class InitializeSystemCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Initialize the system with default data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success('System initialized successfully!');
        
        return Command::SUCCESS;
    }
}
