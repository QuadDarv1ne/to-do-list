<?php

namespace App\Command;

use App\Repository\NotificationRepository;
use App\Repository\PushNotificationRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для очистки старых данных
 *
 * Использование:
 *   php bin/console app:cleanup-data --notifications=90 --audit=180 --dry-run
 */
#[AsCommand(
    name: 'app:cleanup-data',
    description: 'Очистка старых данных (уведомления, audit log, кэш)',
)]
class CleanupDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationRepository $notificationRepo,
        private PushNotificationRepository $pushNotificationRepo,
        private AuditLogService $auditLogService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('notifications', null, InputOption::VALUE_REQUIRED, 'Дней хранения уведомлений', 90)
            ->addOption('audit', null, InputOption::VALUE_REQUIRED, 'Дней хранения audit log', 180)
            ->addOption('push', null, InputOption::VALUE_REQUIRED, 'Дней хранения push-уведомлений', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Режим сухой проверки (без удаления)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Удаление без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧹 Очистка старых данных');

        $notificationsDays = (int) $input->getOption('notifications');
        $auditDays = (int) $input->getOption('audit');
        $pushDays = (int) $input->getOption('push');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->text([
            "Параметры очистки:",
            "  • Уведомления: > {$notificationsDays} дней",
            "  • Audit Log: > {$auditDays} дней",
            "  • Push-уведомления: > {$pushDays} дней",
            "  • Режим: " . ($dryRun ? 'СУХАЯ ПРОВЕРКА' : 'УДАЛЕНИЕ'),
            "",
        ]);

        $totalDeleted = 0;

        // Очистка обычных уведомлений
        $deleted = $this->cleanupNotifications($notificationsDays, $dryRun);
        $io->text("✓ Уведомления: удалено <info>{$deleted}</info> записей");
        $totalDeleted += $deleted;

        // Очистка push-уведомлений
        $deleted = $this->cleanupPushNotifications($pushDays, $dryRun);
        $io->text("✓ Push-уведомления: удалено <info>{$deleted}</info> записей");
        $totalDeleted += $deleted;

        // Очистка Audit Log
        $deleted = $this->cleanupAuditLog($auditDays, $dryRun);
        $io->text("✓ Audit Log: удалено <info>{$deleted}</info> записей");
        $totalDeleted += $deleted;

        // Очистка кэша
        if (!$dryRun) {
            $io->text("✓ Очистка кэша...");
            $this->cleanupCache();
        }

        $io->newLine();
        $io->success("Всего удалено: <info>{$totalDeleted}</info> записей");

        if ($dryRun) {
            $io->warning('Это была сухая проверка. Данные не были удалены.');
            $io->text('Запустите без --dry-run для фактического удаления.');
        }

        return Command::SUCCESS;
    }

    /**
     * Очистка обычных уведомлений
     */
    private function cleanupNotifications(int $days, bool $dryRun): int
    {
        if ($dryRun) {
            // Подсчёт без удаления
            $date = new \DateTime(sprintf('-%d days', $days));
            $count = $this->notificationRepo->countOlderThan($date);
            return $count;
        }

        // Удаляем прочитанные уведомления старше указанного срока
        $date = new \DateTime(sprintf('-%d days', $days));
        return $this->notificationRepo->removeReadOlderThan($date);
    }

    /**
     * Очистка push-уведомлений
     */
    private function cleanupPushNotifications(int $days, bool $dryRun): int
    {
        if ($dryRun) {
            $date = new \DateTime(sprintf('-%d days', $days));
            // Приблизительный подсчёт
            return 0; // Repository method needed
        }

        $date = new \DateTime(sprintf('-%d days', $days));
        $conn = $this->em->getConnection();

        $stmt = $conn->prepare('DELETE FROM push_notifications WHERE created_at < ? AND is_read = 1');
        $stmt->bindValue(1, $date->format('Y-m-d H:i:s'));

        return $stmt->executeStatement();
    }

    /**
     * Очистка Audit Log
     */
    private function cleanupAuditLog(int $days, bool $dryRun): int
    {
        if ($dryRun) {
            $date = new \DateTime(sprintf('-%d days', $days));
            $repo = $this->em->getRepository(\App\Entity\AuditLog::class);
            // Приблизительный подсчёт
            return 0;
        }

        $date = new \DateTime(sprintf('-%d days', $days));
        $conn = $this->em->getConnection();

        $stmt = $conn->prepare('DELETE FROM audit_logs WHERE created_at < ?');
        $stmt->bindValue(1, $date->format('Y-m-d H:i:s'));

        return $stmt->executeStatement();
    }

    /**
     * Очистка кэша
     */
    private function cleanupCache(): void
    {
        try {
            $this->em->clear();
            gc_collect_cycles();
        } catch (\Throwable $e) {
            // Игнорируем ошибки
        }
    }
}
