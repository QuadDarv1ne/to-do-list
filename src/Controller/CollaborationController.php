<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CollaborationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/collaboration')]
#[IsGranted('ROLE_USER')]
class CollaborationController extends AbstractController
{
    public function __construct(
        private CollaborationService $collaborationService,
    ) {
    }

    /**
     * Collaboration dashboard
     */
    #[Route('', name: 'app_collaboration', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $stats = $this->collaborationService->getCollaborationStats($user);
        $collaborators = $this->collaborationService->getMostFrequentCollaborators($user, 10);

        return $this->render('collaboration/index.html.twig', [
            'stats' => $stats,
            'collaborators' => $collaborators,
        ]);
    }

    /**
     * Team workload
     */
    #[Route('/workload', name: 'app_collaboration_workload', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function workload(): Response
    {
        $workload = $this->collaborationService->getTeamWorkload();

        return $this->render('collaboration/workload.html.twig', [
            'workload' => $workload,
        ]);
    }

    /**
     * Collaboration network
     */
    #[Route('/network', name: 'app_collaboration_network', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function network(): Response
    {
        $network = $this->collaborationService->getCollaborationNetwork();

        return $this->render('collaboration/network.html.twig', [
            'network' => $network,
        ]);
    }

    /**
     * Shared tasks with user
     */
    #[Route('/shared/{id}', name: 'app_collaboration_shared', methods: ['GET'])]
    public function shared(User $otherUser): Response
    {
        $user = $this->getUser();
        $sharedTasks = $this->collaborationService->getSharedTasks($user, $otherUser);

        return $this->render('collaboration/shared.html.twig', [
            'other_user' => $otherUser,
            'shared_tasks' => $sharedTasks,
        ]);
    }

    /**
     * Get collaboration stats as JSON
     */
    #[Route('/api/stats', name: 'app_collaboration_api_stats', methods: ['GET'])]
    public function apiStats(): JsonResponse
    {
        $user = $this->getUser();
        $stats = $this->collaborationService->getCollaborationStats($user);

        return $this->json($stats);
    }

    /**
     * Get team workload as JSON
     */
    #[Route('/api/workload', name: 'app_collaboration_api_workload', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function apiWorkload(): JsonResponse
    {
        $workload = $this->collaborationService->getTeamWorkload();

        return $this->json($workload);
    }
}
