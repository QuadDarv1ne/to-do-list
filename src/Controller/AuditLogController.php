<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Audit Log')]
class AuditLogController extends AbstractController
{
    #[Route('', name: 'admin_audit_log', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/audit',
        summary: 'Просмотр Audit Log',
        description: 'Страница просмотра журнала аудита действий пользователей',
        tags: ['Audit Log'],
    )]
    #[OA\Response(response: 200, description: 'Страница журнала аудита')]
    #[OA\Response(response: 403, description: 'Доступ запрещён (требуется роль ADMIN)')]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepo,
    ): Response {
        // Параметры фильтрации
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $action = $request->query->get('action');
        $entityClass = $request->query->get('entity');
        $userId = $request->query->get('user_id');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        //QueryBuilder для фильтрации
        $qb = $auditLogRepo->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        // Фильтры
        if ($action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityClass) {
            $qb->andWhere('a.entityClass LIKE :entity')
                ->setParameter('entity', '%' . $entityClass . '%');
        }

        if ($userId) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $userId);
        }

        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        // Пагинация
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $logs = $qb->getQuery()->getResult();

        // Общее количество записей
        $totalQb = $auditLogRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        // Применяем те же фильтры
        if ($action) {
            $totalQb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }
        if ($entityClass) {
            $totalQb->andWhere('a.entityClass LIKE :entity')
                ->setParameter('entity', '%' . $entityClass . '%');
        }
        if ($userId) {
            $totalQb->andWhere('a.user = :user')
                ->setParameter('user', $userId);
        }
        if ($dateFrom) {
            $totalQb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }
        if ($dateTo) {
            $totalQb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($dateTo . ' 23:59:59'));
        }

        $total = (int) $totalQb->getQuery()->getSingleScalarResult();

        // Уникальные действия для фильтра
        $actions = $auditLogRepo->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/audit_log/index.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'actions' => $actions,
            'filters' => [
                'action' => $action,
                'entity' => $entityClass,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    #[Route('/api', name: 'admin_audit_log_api', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/audit/api',
        summary: 'API для получения Audit Log',
        description: 'Возвращает журнал аудита в формате JSON',
        tags: ['Audit Log'],
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Номер страницы',
        schema: new OA\Schema(type: 'integer', default: 1),
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Количество записей',
        schema: new OA\Schema(type: 'integer', default: 50, maximum: 200),
    )]
    #[OA\Parameter(
        name: 'action',
        in: 'query',
        description: 'Фильтр по действию',
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Список записей аудита',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLog')),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'pages', type: 'integer'),
                ], type: 'object'),
            ],
        ),
    )]
    public function api(
        Request $request,
        AuditLogRepository $auditLogRepo,
    ): JsonResponse {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $action = $request->query->get('action');
        $entityClass = $request->query->get('entity');
        $userId = $request->query->get('user_id');

        $qb = $auditLogRepo->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityClass) {
            $qb->andWhere('a.entityClass LIKE :entity')
                ->setParameter('entity', '%' . $entityClass . '%');
        }

        if ($userId) {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $userId);
        }

        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $logs = $qb->getQuery()->getResult();

        $totalQb = $auditLogRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($action) {
            $totalQb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }
        if ($entityClass) {
            $totalQb->andWhere('a.entityClass LIKE :entity')
                ->setParameter('entity', '%' . $entityClass . '%');
        }
        if ($userId) {
            $totalQb->andWhere('a.user = :user')
                ->setParameter('user', $userId);
        }

        $total = (int) $totalQb->getQuery()->getSingleScalarResult();

        return $this->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], context: ['groups' => ['audit:read']]);
    }

    #[Route('/{id}', name: 'admin_audit_log_view', methods: ['GET'])]
    public function view(AuditLog $log): Response
    {
        return $this->render('admin/audit_log/view.html.twig', [
            'log' => $log,
        ]);
    }

    #[Route('/statistics', name: 'admin_audit_log_statistics', methods: ['GET'])]
    public function statistics(AuditLogRepository $auditLogRepo): JsonResponse
    {
        // Статистика по действиям
        $actions = $auditLogRepo->createQueryBuilder('a')
            ->select('a.action AS action, COUNT(a.id) AS count')
            ->groupBy('a.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        // Статистика по сущностям
        $entities = $auditLogRepo->createQueryBuilder('a')
            ->select('a.entityClass AS entity, COUNT(a.id) AS count')
            ->groupBy('a.entityClass')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Активность по дням (последние 7 дней)
        $dailyActivity = $auditLogRepo->createQueryBuilder('a')
            ->select('DATE(a.createdAt) AS date, COUNT(a.id) AS count')
            ->where('a.createdAt >= :date')
            ->setParameter('date', new \DateTimeImmutable('-7 days'))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        // Топ пользователей по активности
        $topUsers = $auditLogRepo->createQueryBuilder('a')
            ->select('a.userEmail AS email, COUNT(a.id) AS count')
            ->where('a.user IS NOT NULL')
            ->groupBy('a.userEmail')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'by_action' => $actions,
                'by_entity' => $entities,
                'daily_activity' => $dailyActivity,
                'top_users' => $topUsers,
            ],
        ]);
    }
}
