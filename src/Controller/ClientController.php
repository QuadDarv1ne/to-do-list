<?php

namespace App\Controller;

use App\DTO\CreateClientDTO;
use App\DTO\UpdateClientDTO;
use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Service\ClientCommandService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
#[IsGranted('ROLE_USER')]
class ClientController extends AbstractController
{
    #[Route('/', name: 'app_clients_index', methods: ['GET'])]
    public function index(ClientRepository $clientRepository): Response
    {
        $user = $this->getUser();

        // Менеджеры видят только своих клиентов, админы - всех (с оптимизированными запросами)
        $clients = $this->isGranted('ROLE_ADMIN')
            ? $clientRepository->findAllWithRelations()
            : $clientRepository->findByManager($user);

        return $this->render('clients/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/new', name: 'app_clients_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ClientCommandService $clientCommandService,
    ): Response {
        if ($request->isMethod('POST')) {
            // Создаём DTO из запроса
            $dto = CreateClientDTO::fromRequest($request);

            // Используем сервис для создания клиента
            $clientCommandService->createClient($dto, $this->getUser());

            $this->addFlash('success', 'Клиент успешно создан');

            return $this->redirectToRoute('app_clients_index');
        }

        return $this->render('clients/new.html.twig', [
            'client' => null,
        ]);
    }

    #[Route('/{id}', name: 'app_clients_show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        $this->denyAccessUnlessGranted('view', $client);

        return $this->render('clients/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_clients_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Client $client,
        ClientCommandService $clientCommandService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $client);

        if ($request->isMethod('POST')) {
            // Создаём DTO из запроса
            $dto = UpdateClientDTO::fromRequest($request, $client->getId());

            // Используем сервис для обновления клиента
            $clientCommandService->updateClient($dto, $this->getUser());

            $this->addFlash('success', 'Клиент успешно обновлён');

            return $this->redirectToRoute('app_clients_show', ['id' => $client->getId()]);
        }

        return $this->render('clients/edit.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_clients_delete', methods: ['POST'])]
    public function delete(Client $client, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('delete', $client);

        $em->remove($client);
        $em->flush();

        $this->addFlash('success', 'Клиент успешно удалён');

        return $this->redirectToRoute('app_clients_index');
    }

    #[Route('/{id}/interaction/new', name: 'app_client_interaction_new', methods: ['POST'])]
    public function addInteraction(Client $client, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $client);

        $interaction = new \App\Entity\ClientInteraction();
        $interaction->setClient($client);
        $interaction->setUser($this->getUser());
        $interaction->setInteractionType($request->request->get('interaction_type', 'call'));
        $interaction->setDescription($request->request->get('description'));

        $interactionDate = $request->request->get('interaction_date');
        if ($interactionDate) {
            $interaction->setInteractionDate(new \DateTime($interactionDate));
        }

        $client->setLastContactAt(new \DateTime());

        $em->persist($interaction);
        $em->flush();

        $this->addFlash('success', 'Взаимодействие успешно добавлено');

        return $this->redirectToRoute('app_clients_show', ['id' => $client->getId()]);
    }
}
