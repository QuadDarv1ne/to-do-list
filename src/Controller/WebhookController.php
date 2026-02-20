<?php

namespace App\Controller;

use App\Controller\Traits\FlashMessageTrait;
use App\Entity\Webhook;
use App\Form\WebhookType;
use App\Repository\WebhookLogRepository;
use App\Repository\WebhookRepository;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/webhook')]
#[IsGranted('ROLE_USER')]
class WebhookController extends AbstractController
{
    use FlashMessageTrait;

    #[Route('/', name: 'app_webhook_index', methods: ['GET'])]
    public function index(WebhookRepository $webhookRepository): Response
    {
        $user = $this->getUser();

        $webhooks = $webhookRepository->findActiveByUser($user);

        return $this->render('webhook/index.html.twig', [
            'webhooks' => $webhooks,
        ]);
    }

    #[Route('/new', name: 'app_webhook_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        WebhookService $webhookService,
    ): Response {
        $user = $this->getUser();

        $webhook = new Webhook();
        $webhook->setUser($user);

        $form = $this->createForm(WebhookType::class, $webhook);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate secret if not provided
            if (empty($webhook->getSecret())) {
                $webhook->generateSecret();
            }

            $entityManager->persist($webhook);
            $entityManager->flush();

            $this->flashCreated('Webhook успешно создан.');

            return $this->redirectToRoute('app_webhook_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('webhook/new.html.twig', [
            'webhook' => $webhook,
            'form' => $form,
            'availableEvents' => Webhook::getAvailableEvents(),
        ]);
    }

    #[Route('/{id}', name: 'app_webhook_show', methods: ['GET'])]
    public function show(
        Webhook $webhook,
        WebhookLogRepository $logRepository,
        WebhookService $webhookService,
    ): Response {
        $this->denyAccessUnlessGranted('view', $webhook);

        $logs = $logRepository->findByWebhook($webhook->getId(), 50);
        $stats = $webhookService->getWebhookStats($webhook->getId(), 7);
        $successRate = $webhookService->getSuccessRate($webhook->getId(), 7);

        return $this->render('webhook/show.html.twig', [
            'webhook' => $webhook,
            'logs' => $logs,
            'stats' => $stats,
            'successRate' => $successRate,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_webhook_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Webhook $webhook,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $webhook);

        $form = $this->createForm(WebhookType::class, $webhook);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate new secret if field was cleared
            if ($form->get('secret')->getData() === '') {
                $webhook->generateSecret();
            }

            $entityManager->flush();

            $this->flashSuccess('Webhook успешно обновлен.');

            return $this->redirectToRoute('app_webhook_edit', ['id' => $webhook->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('webhook/edit.html.twig', [
            'webhook' => $webhook,
            'form' => $form,
            'availableEvents' => Webhook::getAvailableEvents(),
        ]);
    }

    #[Route('/{id}', name: 'app_webhook_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Webhook $webhook,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('delete', $webhook);

        if ($this->isCsrfTokenValid('delete' . $webhook->getId(), $request->getPayload()->get('_token'))) {
            $webhookName = $webhook->getName();
            $entityManager->remove($webhook);
            $entityManager->flush();

            $this->flashDanger("Webhook '{$webhookName}' удален.");
        }

        return $this->redirectToRoute('app_webhook_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'app_webhook_toggle', methods: ['POST'])]
    public function toggle(
        Webhook $webhook,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $webhook);

        $webhook->setIsActive(!$webhook->isActive());
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $webhook->isActive(),
        ]);
    }

    #[Route('/{id}/test', name: 'app_webhook_test', methods: ['POST'])]
    public function test(
        Webhook $webhook,
        WebhookService $webhookService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $webhook);

        $result = $webhookService->testWebhook($webhook->getUrl(), $webhook->getSecret());

        if ($result['success']) {
            $this->flashSuccess('Тест webhook пройден успешно!');
        } else {
            $this->flashDanger('Ошибка теста webhook: ' . $result['message']);
        }

        return new JsonResponse($result);
    }

    #[Route('/{id}/logs', name: 'app_webhook_logs', methods: ['GET'])]
    public function logs(
        Webhook $webhook,
        WebhookLogRepository $logRepository,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('view', $webhook);

        $limit = $request->query->getInt('limit', 50);
        $logs = $logRepository->findByWebhook($webhook->getId(), $limit);

        return $this->render('webhook/logs.html.twig', [
            'webhook' => $webhook,
            'logs' => $logs,
        ]);
    }

    #[Route('/{id}/retry', name: 'app_webhook_retry', methods: ['POST'])]
    public function retry(
        Webhook $webhook,
        WebhookService $webhookService,
    ): Response {
        $this->denyAccessUnlessGranted('edit', $webhook);

        // Send test event
        $result = $webhookService->sendAndLog($webhook, 'test', [
            'message' => 'Manual retry from CRM',
            'timestamp' => (new \DateTime())->format('c'),
        ]);

        if ($result) {
            $this->flashSuccess('Webhook отправлен успешно!');
        } else {
            $this->flashDanger('Ошибка отправки webhook.');
        }

        return $this->redirectToRoute('app_webhook_show', ['id' => $webhook->getId()], Response::HTTP_SEE_OTHER);
    }
}
