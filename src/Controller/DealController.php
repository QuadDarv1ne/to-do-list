<?php

namespace App\Controller;

use App\DTO\CreateDealDTO;
use App\DTO\UpdateDealDTO;
use App\Entity\Deal;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use App\Service\DealCommandService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/deals')]
#[IsGranted('ROLE_USER')]
class DealController extends AbstractController
{
    #[Route('/', name: 'app_deals_index', methods: ['GET'])]
    public function index(DealRepository $dealRepository): Response
    {
        $user = $this->getUser();

        $deals = $this->isGranted('ROLE_ADMIN')
            ? $dealRepository->findAllWithRelations()
            : $dealRepository->findByManager($user);

        return $this->render('deals/index.html.twig', [
            'deals' => $deals,
        ]);
    }

    #[Route('/funnel', name: 'app_deals_funnel', methods: ['GET'])]
    public function funnel(DealRepository $dealRepository): Response
    {
        $user = $this->getUser();

        $deals = $this->isGranted('ROLE_ADMIN')
            ? $dealRepository->findAllWithRelations()
            : $dealRepository->findByManager($user);

        // Группируем сделки по этапам
        $dealsByStage = [
            'lead' => [],
            'qualification' => [],
            'proposal' => [],
            'negotiation' => [],
            'closing' => [],
        ];

        foreach ($deals as $deal) {
            if ($deal->getStatus() === 'in_progress') {
                $dealsByStage[$deal->getStage()][] = $deal;
            }
        }

        // Считаем статистику
        $stats = [
            'total' => count($deals),
            'in_progress' => count(array_filter($deals, fn($d) => $d->getStatus() === 'in_progress')),
            'won' => count(array_filter($deals, fn($d) => $d->getStatus() === 'won')),
            'lost' => count(array_filter($deals, fn($d) => $d->getStatus() === 'lost')),
        ];

        return $this->render('deals/funnel.html.twig', [
            'dealsByStage' => $dealsByStage,
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'app_deals_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        DealCommandService $dealCommandService,
        ClientRepository $clientRepository,
    ): Response {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            // Создаём DTO из запроса
            $dto = CreateDealDTO::fromRequest($request);

            // Используем сервис для создания сделки
            $deal = $dealCommandService->createDeal($dto, $user);

            $this->addFlash('success', 'Сделка успешно создана');

            return $this->redirectToRoute('app_deals_index');
        }

        $clients = $this->isGranted('ROLE_ADMIN')
            ? $clientRepository->findAll()
            : $clientRepository->findByManager($user);

        return $this->render('deals/new.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/{id}', name: 'app_deals_show', methods: ['GET'])]
    public function show(Deal $deal): Response
    {
        $this->denyAccessUnlessGranted('view', $deal);

        return $this->render('deals/show.html.twig', [
            'deal' => $deal,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_deals_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Deal $deal,
        DealCommandService $dealCommandService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $deal);

        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            // Создаём DTO из запроса
            $dto = UpdateDealDTO::fromRequest($request, $deal->getId());

            // Используем сервис для обновления сделки
            $dealCommandService->updateDeal($dto, $user);

            $this->addFlash('success', 'Сделка успешно обновлена');

            return $this->redirectToRoute('app_deals_show', ['id' => $deal->getId()]);
        }

        return $this->render('deals/edit.html.twig', [
            'deal' => $deal,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_deals_delete', methods: ['POST'])]
    public function delete(Deal $deal, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('delete', $deal);

        $em->remove($deal);
        $em->flush();

        $this->addFlash('success', 'Сделка успешно удалена');

        return $this->redirectToRoute('app_deals_index');
    }

    #[Route('/{id}/win', name: 'app_deals_win', methods: ['POST'])]
    public function win(
        Deal $deal,
        DealCommandService $dealCommandService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $deal);

        $user = $this->getUser();
        $dealCommandService->winDeal($deal, $user);

        $this->addFlash('success', 'Сделка успешно выиграна');

        return $this->redirectToRoute('app_deals_show', ['id' => $deal->getId()]);
    }

    #[Route('/{id}/lose', name: 'app_deals_lose', methods: ['POST'])]
    public function lose(
        Request $request,
        Deal $deal,
        DealCommandService $dealCommandService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $deal);

        $user = $this->getUser();
        $reason = $request->request->get('lost_reason', 'Без указания причины');

        $dealCommandService->loseDeal($deal, $user, $reason);

        $this->addFlash('success', 'Сделка отклонена');

        return $this->redirectToRoute('app_deals_show', ['id' => $deal->getId()]);
    }
}
