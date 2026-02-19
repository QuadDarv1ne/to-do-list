<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, ActivityLogRepository $activityLogRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('security_controller_login');
        }

        // Если пользователь уже авторизован, перенаправляем на главную
        if ($this->getUser()) {

            try {
                return $this->redirectToRoute('app_dashboard');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('security_controller_login');
                }
            }
        }

        // Получаем ошибку входа, если есть
        $error = $authenticationUtils->getLastAuthenticationError();

        // Последний введенный email
        $lastUsername = $authenticationUtils->getLastUsername();

        // Проверяем, был ли редирект после истечения сессии
        $sessionExpired = $request->query->getBoolean('expired');

        try {
            return $this->render('security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
                'session_expired' => $sessionExpired,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('security_controller_login');
            }
        }
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Этот метод может быть пустым - он будет перехвачен системой безопасности
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/access-denied', name: 'app_access_denied')]
    public function accessDenied(?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('security_controller_access_denied');
        }

        try {
            return $this->render('security/access_denied.html.twig', [], new Response('Доступ запрещен', 403));
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('security_controller_access_denied');
            }
        }
    }
}
