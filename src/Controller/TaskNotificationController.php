<?php

namespace App\Controller;

use App\Entity\TaskNotification;
use App\Form\TaskNotificationType;
use App\Repository\TaskNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-notifications')]
#[IsGranted('ROLE_USER')]
class TaskNotificationController extends AbstractController
{
    #[Route('/', name: 'app_task_notification_index', methods: ['GET'])]
    public function index(TaskNotificationRepository $taskNotificationRepository): Response
    {
        $user = $this->getUser();
        // Only show notifications for the current user
        $notifications = $taskNotificationRepository->findForUser($user);

        return $this->render('task_notification/index.html.twig', [
            'task_notifications' => $notifications,
        ]);
    }

    #[Route('/new', name: 'app_task_notification_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Only admins or authorized users can create notifications
        $this->denyAccessUnlessGranted('create', TaskNotification::class);
        $taskNotification = new TaskNotification();
        $form = $this->createForm(TaskNotificationType::class, $taskNotification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taskNotification->setSender($this->getUser());
            $entityManager->persist($taskNotification);
            $entityManager->flush();

            $this->addFlash('success', 'Уведомление создано успешно!');

            return $this->redirectToRoute('app_task_notification_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_notification/new.html.twig', [
            'task_notification' => $taskNotification,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_notification_show', methods: ['GET'])]
    public function show(TaskNotification $taskNotification, EntityManagerInterface $entityManager): Response
    {
        // Ensure user can only view their own notifications
        $this->denyAccessUnlessGranted('view', $taskNotification);

        // Mark notification as read when viewed
        if (!$taskNotification->isIsRead()) {
            $taskNotification->setIsRead(true);
            $entityManager->flush();
        }

        return $this->render('task_notification/show.html.twig', [
            'task_notification' => $taskNotification,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_notification_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskNotification $taskNotification, EntityManagerInterface $entityManager): Response
    {
        // Ensure user can only edit their own notifications
        $this->denyAccessUnlessGranted('edit', $taskNotification);
        $form = $this->createForm(TaskNotificationType::class, $taskNotification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Уведомление обновлено успешно!');

            return $this->redirectToRoute('app_task_notification_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_notification/edit.html.twig', [
            'task_notification' => $taskNotification,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_notification_delete', methods: ['POST'])]
    public function delete(Request $request, TaskNotification $taskNotification, EntityManagerInterface $entityManager): Response
    {
        // Ensure user can only delete their own notifications
        $this->denyAccessUnlessGranted('delete', $taskNotification);
        if ($this->isCsrfTokenValid('delete'.$taskNotification->getId(), $request->request->get('_token'))) {
            $entityManager->remove($taskNotification);
            $entityManager->flush();

            $this->addFlash('success', 'Уведомление удалено успешно!');
        }

        return $this->redirectToRoute('app_task_notification_index', [], Response::HTTP_SEE_OTHER);
    }
}
