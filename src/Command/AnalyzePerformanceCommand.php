<?php

namespace App\Command;

use App\Service\DatabaseOptimizationService;
use App\Service\QueryPerformanceMonitor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-performance',
    description: 'Анализ производительности приложения',
)]
class AnalyzePerformanceCommand extends Command
{
    public function __construct(
        private DatabaseOptimizationService $dbOptimizer,
        private QueryPerformanceMonitor $queryMonitor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Анализ производительности приложения');

        // Анализ БД
        $io->section('Анализ базы данных');

        try {
            $dbAnalysis = $this->dbOptimizer->analyzeQueryPerformance();

            if (isset($dbAnalysis['connection_stats'])) {
                $stats = $dbAnalysis['connection_stats'];
                $io->text(\sprintf(
                    'Подключения: %d всего, %d активных',
                    $stats['total_connections'] ?? 0,
                    $stats['active_connections'] ?? 0,
                ));
            }

            if (isset($dbAnalysis['recommendations']) && \is_array($dbAnalysis['recommendations'])) {
                if (\count($dbAnalysis['recommendations']) > 0) {
                    $io->warning('Найдено рекомендаций: ' . \count($dbAnalysis['recommendations']));
                    foreach (\array_slice($dbAnalysis['recommendations'], 0, 5) as $rec) {
                        $io->text('• ' . $rec['message']);
                    }
                } else {
                    $io->success('Проблем не обнаружено');
                }
            }
        } catch (\Exception $e) {
            $io->error('Ошибка анализа БД: ' . $e->getMessage());
        }

        // Статистика запросов
        $io->section('Статистика запросов');
        $queryStats = $this->queryMonitor->getStatistics();
        $io->text(\sprintf(
            'Всего запросов: %d, Медленных: %d',
            $queryStats['total_queries'],
            $queryStats['slow_queries'],
        ));

        // N+1 проблемы
        $nPlusOne = $this->queryMonitor->detectNPlusOne();
        if (\count($nPlusOne) > 0) {
            $io->warning('Обнаружены потенциальные N+1 проблемы: ' . \count($nPlusOne));
            foreach (\array_slice($nPlusOne, 0, 3) as $problem) {
                $io->text(\sprintf('• Запрос выполнен %d раз', $problem['count']));
            }
        } else {
            $io->success('N+1 проблемы не обнаружены');
        }

        $io->success('Анализ завершен');

        return Command::SUCCESS;
    }
}
