<?php

namespace App\Controller;

use App\Entity\Goal;
use App\Repository\GoalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/goals')]
#[IsGranted('ROLE_USER')]
class GoalController extends AbstractController
{
    #[Route('/', name: 'app_goals_index', methods: ['GET'])]
    public function index(GoalRepository $goalRepository): Response
    {
        $user = $this->getUser();
        $goals = $goalRepository->findActiveGoalsByUser($user);

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
}
