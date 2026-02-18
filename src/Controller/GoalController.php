<?php

namespace App\Controller;

use App\Entity\Goal;
use App\Entity\GoalMilestone;
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
    #[Route('/', name: 'app_goals_index')]
    public function index(GoalRepository $goalRepository): Response
    {
        $user = $this->getUser();
        $activeGoals = $goalRepository->findActiveGoalsByUser($user);
        $completedGoals = $goalRepository->findGoalsByStatus($user, 'completed');
        
        return $this->render('goals/index.html.twig', [
            'activeGoals' => $activeGoals,
            'completedGoals' => $completedGoals,
        ]);
    }

    #[Route('/create', name: 'app_goals_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $goal = new Goal();
        $goal->setTitle($data['title']);
        $goal->setDescription($data['description'] ?? null);
        $goal->setOwner($this->getUser());
        $goal->setStartDate(new \DateTime($data['startDate']));
        $goal->setEndDate(new \DateTime($data['endDate']));
        $goal->setTargetValue($data['targetValue']);
        $goal->setPriority($data['priority'] ?? 'medium');
        
        $em->persist($goal);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'goal' => [
                'id' => $goal->getId(),
                'title' => $goal->getTitle(),
                'progress' => $goal->getProgress()
            ]
        ]);
    }

    #[Route('/{id}/update-progress', name: 'app_goals_update_progress', methods: ['POST'])]
    public function updateProgress(Goal $goal, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $goal);
        
        $data = json_decode($request->getContent(), true);
        $goal->setCurrentValue($data['currentValue']);
        $goal->setUpdatedAt(new \DateTime());
        
        if ($goal->getProgress() >= 100) {
            $goal->setStatus('completed');
        }
        
        $em->flush();
        
        return $this->json([
            'success' => true,
            'progress' => $goal->getProgress()
        ]);
    }

    #[Route('/{id}/milestone', name: 'app_goals_add_milestone', methods: ['POST'])]
    public function addMilestone(Goal $goal, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $goal);
        
        $data = json_decode($request->getContent(), true);
        
        $milestone = new GoalMilestone();
        $milestone->setGoal($goal);
        $milestone->setTitle($data['title']);
        $milestone->setDescription($data['description'] ?? null);
        $milestone->setDueDate(new \DateTime($data['dueDate']));
        
        $em->persist($milestone);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'milestone' => [
                'id' => $milestone->getId(),
                'title' => $milestone->getTitle()
            ]
        ]);
    }

    #[Route('/milestone/{id}/toggle', name: 'app_goals_toggle_milestone', methods: ['POST'])]
    public function toggleMilestone(GoalMilestone $milestone, EntityManagerInterface $em): JsonResponse
    {
        $milestone->setCompleted(!$milestone->isCompleted());
        $em->flush();
        
        return $this->json([
            'success' => true,
            'completed' => $milestone->isCompleted()
        ]);
    }

    #[Route('/{id}/delete', name: 'app_goals_delete', methods: ['DELETE'])]
    public function delete(Goal $goal, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $goal);
        
        $em->remove($goal);
        $em->flush();
        
        return $this->json(['success' => true]);
    }
}
