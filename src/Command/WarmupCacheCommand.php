<?php

namespace App\Command;

use App\Service\DataCacheWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:warmup-data',
    description: 'Прогрев кэша данных приложения',
)]
class WarmupCacheCommand extends Command
{
    public function __construct(
        private DataCacheWarmer $cacheWarmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clear', 'c', InputOption::VALUE_NONE, 'Очистить кэш перед прогревом');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Прогрев кэша данных');

        if ($input->getOption('clear')) {
            $io->text('Очистка существующего кэша...');
            $this->cacheWarmer->clearAll();
            $io->success('Кэш очищен');
        }

        $io->text('Прогрев кэша для всех пользователей...');
        $startTime = microtime(true);

        $results = $this->cacheWarmer->warmupAll();

        $duration = round(microtime(true) - $startTime, 2);

        $io->success(\sprintf(
            'Прогрев завершен за %s сек. Обработано: %d пользователей, создано: %d записей, ошибок: %d',
            $duration,
            $results['users_processed'],
            $results['cache_entries'],
            $results['errors'],
        ));

        return Command::SUCCESS;
    }
}
