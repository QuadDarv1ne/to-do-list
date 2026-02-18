<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
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
        
        // Менеджеры видят только своих клиентов, админы - всех
        $clients = $this->isGranted('ROLE_ADMIN') 
            ? $clientRepository->findAll()
            : $clientRepository->findByManager($user);

        return $this->render('clients/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/new', name: 'app_clients_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $client = new Client();
        $client->setManager($this->getUser());

        if ($request->isMethod('POST')) {
            $client->setCompanyName($request->request->get('company_name'));
            $client->setInn($request->request->get('inn'));
            $client->setKpp($request->request->get('kpp'));
            $client->setContactPerson($request->request->get('contact_person'));
            $client->setPhone($request->request->get('phone'));
            $client->setEmail($request->request->get('email'));
            $client->setAddress($request->request->get('address'));
            $client->setSegment($request->request->get('segment', 'retail'));
            $client->setCategory($request->request->get('category', 'new'));
            $client->setNotes($request->request->get('notes'));

            $em->persist($client);
            $em->flush();

            $this->addFlash('success', 'Клиент успешно создан');
            return $this->redirectToRoute('app_clients_index');
        }

        return $this->render('clients/new.html.twig', [
            'client' => $client,
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
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $client);

        if ($request->isMethod('POST')) {
            $client->setCompanyName($request->request->get('company_name'));
            $client->setInn($request->request->get('inn'));
            $client->setKpp($request->request->get('kpp'));
            $client->setContactPerson($request->request->get('contact_person'));
            $client->setPhone($request->request->get('phone'));
            $client->setEmail($request->request->get('email'));
            $client->setAddress($request->request->get('address'));
            $client->setSegment($request->request->get('segment'));
            $client->setCategory($request->request->get('category'));
            $client->setNotes($request->request->get('notes'));
            $client->setUpdatedAt(new \DateTime());

            $em->flush();

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
}
