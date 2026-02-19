<?php

namespace App\Controller;

use App\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/backup')]
#[IsGranted('ROLE_ADMIN')]
class BackupController extends AbstractController
{
    public function __construct(
        private BackupService $backupService,
    ) {
    }

    #[Route('', name: 'app_backup_index', methods: ['GET'])]
    public function index(): Response
    {
        $backups = $this->backupService->listBackups();
        $totalSize = $this->backupService->getTotalBackupSize();

        return $this->render('backup/index.html.twig', [
            'backups' => $backups,
            'totalSize' => $totalSize,
        ]);
    }

    #[Route('/create', name: 'app_backup_create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        $result = $this->backupService->createFullBackup();

        if ($result['success']) {
            $this->addFlash('success', 'Бэкап успешно создан');
        } else {
            $this->addFlash('error', 'Ошибка создания бэкапа: ' . $result['error']);
        }

        return $this->json($result);
    }

    #[Route('/create-incremental', name: 'app_backup_create_incremental', methods: ['POST'])]
    public function createIncremental(): JsonResponse
    {
        $since = new \DateTime('-1 day');
        $result = $this->backupService->createIncrementalBackup($since);

        if ($result['success']) {
            $this->addFlash('success', 'Инкрементальный бэкап создан');
        } else {
            $this->addFlash('error', 'Ошибка: ' . $result['error']);
        }

        return $this->json($result);
    }

    #[Route('/clean', name: 'app_backup_clean', methods: ['POST'])]
    public function clean(): JsonResponse
    {
        $deleted = $this->backupService->cleanOldBackups(30);

        $this->addFlash('success', "Удалено старых бэкапов: {$deleted}");

        return $this->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    #[Route('/download/{filename}', name: 'app_backup_download', methods: ['GET'])]
    public function download(string $filename): Response
    {
        $filepath = $this->getParameter('kernel.project_dir') . '/var/backups/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Файл не найден');
        }

        return $this->file($filepath);
    }
}
