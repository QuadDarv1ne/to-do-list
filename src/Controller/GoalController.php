<?php

namespace App\Controller;

use App\Entity\Goal;
use App\Repository\GoalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/goals')]
#[IsGranted('ROLE_USER')]
class GoalController extends AbstractController
{
    public function __construct(
        private GoalRepository $goalRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'app_goals_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $goals = $this->goalRepository->findActiveGoalsByUser($user);

        return $this->render('goals/index.html.twig', [
            'goals' => $goals,
        ]);
    }

    #[Route('/{id}', name: 'app_goals_show', methods: ['GET'])]
    public function show(Goal $goal): Response
    {
        $this->denyAccessUnlessGranted('view', $goal);

        return $this->render('goals/show.html.twig', [
            'goal' => $goal,
        ]);
    }

    #[Route('/{id}/update-progress', name: 'app_goals_update_progress', methods: ['POST'])]
    public function updateProgress(Request $request, Goal $goal): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $goal);

        $data = json_decode($request->getContent(), true);
        $newProgress = (int) ($data['progress'] ?? $goal->getProgress());

        // Ограничиваем прогресс от 0 до 100
        $newProgress = max(0, min(100, $newProgress));

        $goal->setProgress($newProgress);
        $goal->setUpdatedAt(new \DateTime());

        // Если прогресс 100%, помечаем цель как завершённую
        if ($newProgress === 100) {
            $goal->setCompleted(true);
            $goal->setCompletedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'progress' => $goal->getProgress(),
            'completed' => $goal->isCompleted(),
        ]);
    }
}
