<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    #[Route('', name: 'app_audit_log', methods: ['GET'])]
    public function index(AuditLogRepository $auditLogRepository): Response
    {
        $auditLogs = $auditLogRepository->findLatest(50);

        return $this->render('audit_log/index.html.twig', [
            'audit_logs' => $auditLogs,
        ]);
    }

    #[Route('/{id}', name: 'app_audit_log_show', methods: ['GET'])]
    public function show(AuditLog $auditLog): Response
    {
        return $this->render('audit_log/show.html.twig', [
            'audit_log' => $auditLog,
        ]);
    }

    #[Route('/entity/{class}/{id}', name: 'app_audit_log_entity', methods: ['GET'])]
    public function byEntity(
        string $class,
        int $id,
        AuditLogRepository $auditLogRepository
    ): Response {
        $auditLogs = $auditLogRepository->findByEntity($class, $id);

        return $this->render('audit_log/index.html.twig', [
            'audit_logs' => $auditLogs,
            'entity_class' => $class,
            'entity_id' => $id,
        ]);
    }

    #[Route('/user/{userId}', name: 'app_audit_log_user', methods: ['GET'])]
    public function byUser(
        int $userId,
        AuditLogRepository $auditLogRepository
    ): Response {
        $auditLogs = $auditLogRepository->findByUser($userId);

        return $this->render('audit_log/index.html.twig', [
            'audit_logs' => $auditLogs,
            'user_id' => $userId,
        ]);
    }
}
