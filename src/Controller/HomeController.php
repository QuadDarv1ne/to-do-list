<?php

namespace App\Controller;

use App\Service\LoggingService;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(
        AuthenticationUtils $authenticationUtils,
        ?PerformanceMonitorService $performanceMonitor = null,
        ?LoggingService $loggingService = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('home_controller_index');
        }
        
        // Log home page access
        if ($loggingService) {
            $loggingService->logInfo('Home page accessed');
        }
        
        // If user is already logged in, redirect to dashboard
        if ($this->getUser()) {
            if ($loggingService) {
                $loggingService->logUserActivity('Redirected from home to dashboard');
            }
            
            try {
                return $this->redirectToRoute('app_dashboard');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('home_controller_index');
                }
            }
        }

        // Otherwise, redirect to login
        if ($loggingService) {
            $loggingService->logUserActivity('Redirected from home to login');
        }
        
        try {
            return $this->redirectToRoute('app_login');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('home_controller_index');
            }
        }
    }
}
