<?php

namespace App\Controller;

use App\Entity\Deal;
use App\Repository\DealRepository;
use App\Repository\ClientRepository;
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
        
        // Менеджеры видят только свои сделки, админы - все
        $deals = $this->isGranted('ROLE_ADMIN') 
            ? $dealRepository->findAll()
            : $dealRepository->findByManager($user);

        return $this->render('deals/index.html.twig', [
            'deals' => $deals,
        ]);
    }

    #[Route('/funnel', name: 'app_deals_funnel', methods: ['GET'])]
    public function funnel(DealRepository $dealRepository): Response
    {
        $user = $this->getUser();
        
        $funnelData = $this->isGranted('ROLE_ADMIN')
            ? $dealRepository->getDealsByStage()
            : $dealRepository->getDealsByStage($user);

        return $this->render('deals/funnel.html.twig', [
            'funnel_data' => $funnelData,
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
            $deal->setAmount($request->request->get('amount'));
            $deal->setStage($request->request->get('stage', 'lead'));
            $deal->setDescription($request->request->get('description'));
            
            $expectedDate = $request->request->get('expected_close_date');
            if ($expectedDate) {
                $deal->setExpectedCloseDate(new \DateTime($expectedDate));
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
            
            $expectedDate = $request->request->get('expected_close_date');
            if ($expectedDate) {
                $deal->setExpectedCloseDate(new \DateTime($expectedDate));
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
