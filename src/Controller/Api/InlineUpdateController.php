<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Entity\Client;
use App\Entity\Deal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security as CoreSecurity;

#[Route('/api/inline-update', name: 'api_inline_update')]
class InlineUpdateController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CoreSecurity $security
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $entityType = $data['entityType'] ?? null;
        $entityId = $data['entityId'] ?? null;
        $fieldName = $data['fieldName'] ?? null;
        $value = $data['value'] ?? null;

        if (!$entityType || !$entityId || !$fieldName) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        $entity = $this->findEntity($entityType, $entityId);

        if (!$entity) {
            return $this->json(['error' => 'Entity not found'], 404);
        }

        // Проверка доступа
        if (!$this->canEdit($entity)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Обновление поля
        $this->updateField($entity, $fieldName, $value);

        try {
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'value' => $this->getFieldValue($entity, $fieldName),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function findEntity(string $type, int $id): ?object
    {
        return match($type) {
            'task' => $this->entityManager->getRepository(Task::class)->find($id),
            'client' => $this->entityManager->getRepository(Client::class)->find($id),
            'deal' => $this->entityManager->getRepository(Deal::class)->find($id),
            default => null,
        };
    }

    private function canEdit(object $entity): bool
    {
        $user = $this->security->getUser();

        if (!$user) {
            return false;
        }

        // Владелец может редактировать свои сущности
        if (method_exists($entity, 'getUser') && $entity->getUser() === $user) {
            return true;
        }

        // Назначенный пользователь может редактировать
        if (method_exists($entity, 'getAssignedUser') && $entity->getAssignedUser() === $user) {
            return true;
        }

        // Админы могут всё
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    private function updateField(object $entity, string $fieldName, mixed $value): void
    {
        $setter = 'set' . ucfirst($fieldName);

        if (method_exists($entity, $setter)) {
            // Обработка специальных типов
            if (in_array($fieldName, ['status', 'priority'])) {
                $value = strtolower($value);
            }

            $entity->$setter($value);
        }
    }

    private function getFieldValue(object $entity, string $fieldName): mixed
    {
        $getter = 'get' . ucfirst($fieldName);

        if (method_exists($entity, $getter)) {
            $value = $entity->$getter();

            // Форматирование для datetime
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d.m.Y');
            }

            return $value;
        }

        return null;
    }
}
