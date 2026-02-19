<?php

namespace App\EventListener;

use App\Repository\TaskRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class TwigGlobalListener
{
    public function __construct(
        private Environment $twig,
        private TaskRepository $taskRepository,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // Only for main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Get current user
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            // Set default empty stats for non-authenticated users
            $this->twig->addGlobal('task_stats', [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
            ]);

            return;
        }

        $user = $token->getUser();

        try {
            // Get task stats for current user
            $taskStats = $this->taskRepository->getQuickStats($user);

            // Add to Twig globals
            $this->twig->addGlobal('task_stats', $taskStats);
        } catch (\Exception $e) {
            // If error occurs, set default empty stats
            $this->twig->addGlobal('task_stats', [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
            ]);
        }
    }
}
