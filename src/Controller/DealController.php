<?php

namespace App\Controller;

use App\Entity\Deal;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
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
    public function new(Request $request, EntityManagerInterface $em, ClientRepository $clientRepository): Response
    {
        $deal = new Deal();
        $deal->setManager($this->getUser());

        if ($request->isMethod('POST')) {
            $clientId = $request->request->get('client_id');
            $client = $clientRepository->find($clientId);

            if (!$client) {
                $this->addFlash('error', 'Клиент не найден');
                return $this->redirectToRoute('app_deals_new');
            }

            $deal->setTitle($request->request->get('title'));
            $deal->setClient($client);
            $deal->setAmount($request->request->get('amount', '0.00'));
            $deal->setStage($request->request->get('stage', 'lead'));
            $deal->setDescription($request->request->get('description'));

            $expectedCloseDate = $request->request->get('expected_close_date');
            if ($expectedCloseDate) {
                $deal->setExpectedCloseDate(new \DateTime($expectedCloseDate));
            }

            $em->persist($deal);
            $em->flush();

            $this->addFlash('success', 'Сделка успешно создана');

            return $this->redirectToRoute('app_deals_index');
        }

        $clients = $this->isGranted('ROLE_ADMIN')
            ? $clientRepository->findAll()
            : $clientRepository->findByManager($this->getUser());

        return $this->render('deals/new.html.twig', [
            'deal' => $deal,
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
    public function edit(Request $request, Deal $deal, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $deal);

        if ($request->isMethod('POST')) {
            $deal->setTitle($request->request->get('title'));
            $deal->setAmount($request->request->get('amount'));
            $deal->setStage($request->request->get('stage'));
            $deal->setStatus($request->request->get('status'));
            $deal->setDescription($request->request->get('description'));

            $expectedCloseDate = $request->request->get('expected_close_date');
            if ($expectedCloseDate) {
                $deal->setExpectedCloseDate(new \DateTime($expectedCloseDate));
            }

            if ($request->request->get('status') === 'lost') {
                $deal->setLostReason($request->request->get('lost_reason'));
            }

            $deal->setUpdatedAt(new \DateTime());

            $em->flush();

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
}
