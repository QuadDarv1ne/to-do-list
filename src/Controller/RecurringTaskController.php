<?php

namespace App\Controller;

use App\Controller\Traits\FlashMessageTrait;
use App\Entity\TaskRecurrence;
use App\Form\TaskRecurrenceType;
use App\Service\RecurringTaskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recurring')]
#[IsGranted('ROLE_USER')]
class RecurringTaskController extends AbstractController
{
    use FlashMessageTrait;

    #[Route('/', name: 'app_recurring_index', methods: ['GET'])]
    public function index(RecurringTaskService $recurringService): Response
    {
        $user = $this->getUser();
        $recurrences = $recurringService->getUserRecurringTasks($user);
        $stats = $recurringService->getStatistics($user);

        return $this->render('recurring/index.html.twig', [
            'recurrences' => $recurrences,
            'stats' => $stats,
            'patterns' => $recurringService->getPatterns(),
        ]);
    }

    #[Route('/new', name: 'app_recurring_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        RecurringTaskService $recurringService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        $recurrence = new TaskRecurrence();
        $recurrence->setUser($user);

        $form = $this->createForm(TaskRecurrenceType::class, $recurrence, [
            'user' => $user,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recurringService->createRecurring(
                $recurrence->getTask(),
                $user,
                $recurrence->getFrequency(),
                $recurrence->getInterval() ?? 1,
                $recurrence->getEndDate(),
                $recurrence->getDaysOfWeekArray(),
                $recurrence->getDaysOfMonthArray(),
            );

            $this->flashCreated('Правило повторения создано.');

            return $this->redirectToRoute('app_recurring_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurring/new.html.twig', [
            'recurrence' => $recurrence,
            'form' => $form,
            'patterns' => $recurringService->getPatterns(),
            'daysOfWeek' => $recurringService->getDaysOfWeekOptions(),
            'daysOfMonth' => $recurringService->getDaysOfMonthOptions(),
        ]);
    }

    #[Route('/{id}', name: 'app_recurring_show', methods: ['GET'])]
    public function show(TaskRecurrence $recurrence, RecurringTaskService $recurringService): Response
    {
        $this->denyAccessUnlessGranted('view', $recurrence);

        $nextCreation = $this->calculateNextCreation($recurrence);

        return $this->render('recurring/show.html.twig', [
            'recurrence' => $recurrence,
            'nextCreation' => $nextCreation,
            'stats' => $recurringService->getStatistics($recurrence->getUser()),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recurring_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TaskRecurrence $recurrence,
        RecurringTaskService $recurringService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $recurrence);

        $form = $this->createForm(TaskRecurrenceType::class, $recurrence, [
            'user' => $recurrence->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recurringService->updateRecurring(
                $recurrence,
                $recurrence->getFrequency(),
                $recurrence->getInterval(),
                $recurrence->getEndDate(),
                $recurrence->getDaysOfWeekArray(),
                $recurrence->getDaysOfMonthArray(),
            );

            $this->flashSuccess('Правило повторения обновлено.');

            return $this->redirectToRoute('app_recurring_show', ['id' => $recurrence->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recurring/edit.html.twig', [
            'recurrence' => $recurrence,
            'form' => $form,
            'patterns' => $recurringService->getPatterns(),
        ]);
    }

    #[Route('/{id}', name: 'app_recurring_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        TaskRecurrence $recurrence,
        RecurringTaskService $recurringService,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('delete', $recurrence);

        if ($this->isCsrfTokenValid('delete' . $recurrence->getId(), $request->getPayload()->get('_token'))) {
            $deleteCreated = $request->getPayload()->getBoolean('delete_created', false);
            $recurringService->deleteRecurring($recurrence, $deleteCreated);

            $this->flashDanger('Правило повторения удалено.');
        }

        return $this->redirectToRoute('app_recurring_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'app_recurring_toggle', methods: ['POST'])]
    public function toggle(TaskRecurrence $recurrence, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $recurrence);

        // Toggle by setting/unsetting end date
        if ($recurrence->getEndDate() !== null) {
            $recurrence->setEndDate(null);
        } else {
            $recurrence->setEndDate(new \DateTimeImmutable());
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $recurrence->getEndDate() === null || $recurrence->getEndDate() >= new \DateTimeImmutable(),
        ]);
    }

    #[Route('/process', name: 'app_recurring_process', methods: ['POST'])]
    public function process(RecurringTaskService $recurringService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $created = $recurringService->processRecurringTasks();

        return new JsonResponse([
            'success' => true,
            'created_count' => count($created),
            'message' => sprintf('Создано задач: %d', count($created)),
        ]);
    }

    private function calculateNextCreation(TaskRecurrence $recurrence): ?\DateTimeImmutable
    {
        $lastGenerated = $recurrence->getLastGenerated();
        $frequency = $recurrence->getFrequency();
        $interval = $recurrence->getInterval() ?? 1;

        if ($lastGenerated === null) {
            return new \DateTimeImmutable();
        }

        return match($frequency) {
            'daily' => $lastGenerated->modify("+{$interval} days"),
            'weekly' => $lastGenerated->modify("+{$interval} weeks"),
            'monthly' => $lastGenerated->modify("+{$interval} months"),
            'yearly' => $lastGenerated->modify("+{$interval} years"),
            default => null
        };
    }
}
