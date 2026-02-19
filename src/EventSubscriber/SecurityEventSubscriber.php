<?php

namespace App\EventSubscriber;

use App\Repository\ActivityLogRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request ? $request->getClientIp() : null;

        $this->activityLogRepository->logLoginEvent($user, $ipAddress);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        // Check if user is an actual user object (not anonymous)
        if ($user && \is_object($user) && method_exists($user, 'getId')) {
            $request = $this->requestStack->getCurrentRequest();
            $ipAddress = $request ? $request->getClientIp() : null;

            $this->activityLogRepository->logLogoutEvent($user, $ipAddress);
        }
    }
}
