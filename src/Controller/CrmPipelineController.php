<?php

namespace App\Controller;

use App\Entity\Deal;
use App\Repository\DealRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm/pipeline')]
#[IsGranted('ROLE_MANAGER')]
class CrmPipelineController extends AbstractController
{
    public function __construct(
        private readonly DealRepository $dealRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_crm_pipeline', methods: ['GET'])]
    public function index(): Response
    {
        $deals = $this->dealRepository->findBy(['status' => 'in_progress'], ['createdAt' => 'DESC']);
        
        $stages = [
            'lead' => ['label' => 'Лид', 'color' => 'gray'],
            'qualification' => ['label' => 'Квалификация', 'color' => 'blue'],
            'proposal' => ['label' => 'Предложение', 'color' => 'yellow'],
            'negotiation' => ['label' => 'Переговоры', 'color' => 'orange'],
            'closing' => ['label' => 'Закрытие', 'color' => 'green'],
        ];

        $dealsByStage = [];
        foreach ($stages as $stage => $info) {
            $dealsByStage[$stage] = [
                'label' => $info['label'],
                'color' => $info['color'],
                'deals' => array_filter($deals, fn($deal) => $deal->getStage() === $stage),
            ];
        }

        return $this->render('crm/pipeline/index.html.twig', [
            'deals_by_stage' => $dealsByStage,
            'stages' => $stages,
        ]);
    }

    #[Route('/api', name: 'app_crm_pipeline_api', methods: ['GET'])]
    public function api(): JsonResponse
    {
        $deals = $this->dealRepository->findBy(['status' => 'in_progress'], ['createdAt' => 'DESC']);
        
        $stages = [
            'lead' => 'Лид',
            'qualification' => 'Квалификация',
            'proposal' => 'Предложение',
            'negotiation' => 'Переговоры',
            'closing' => 'Закрытие',
        ];

        $result = [];
        foreach ($stages as $stage => $label) {
            $stageDeals = array_values(array_map(fn($deal) => [
                'id' => $deal->getId(),
                'title' => $deal->getTitle(),
                'amount' => $deal->getAmount(),
                'client' => $deal->getClient()?->getCompanyName(),
                'manager' => $deal->getManager()?->getDisplayName(),
                'created_at' => $deal->getCreatedAt()?->format('Y-m-d'),
            ], array_filter($deals, fn($deal) => $deal->getStage() === $stage)));

            $result[] = [
                'stage' => $stage,
                'label' => $label,
                'deals' => $stageDeals,
                'total_amount' => array_sum(array_column($stageDeals, 'amount')),
                'count' => count($stageDeals),
            ];
        }

        return $this->json($result);
    }

    #[Route('/{id}/move', name: 'app_crm_pipeline_move', methods: ['POST'])]
    public function move(Deal $deal, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStage = $data['stage'] ?? null;

        $validStages = ['lead', 'qualification', 'proposal', 'negotiation', 'closing'];
        if (!in_array($newStage, $validStages, true)) {
            return $this->json(['error' => 'Invalid stage'], Response::HTTP_BAD_REQUEST);
        }

        $oldStage = $deal->getStage();
        $deal->setStage($newStage);
        
        // Auto-update status when moving to closing
        if ($newStage === 'closing') {
            $deal->setStatus('won');
            $deal->setActualCloseDate(new \DateTime());
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Сделка перемещена из "'.$oldStage.'" в "'.$newStage.'"');

        return $this->json(['success' => true, 'deal_id' => $deal->getId()]);
    }
}
