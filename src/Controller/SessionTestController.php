<?php
// src/Controller/SessionTestController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SessionTestController extends AbstractController
{
    #[Route('/test/session', name: 'app_test_session')]
    public function testSession(): Response
    {
        $session = $this->container->get('request_stack')->getSession();
        
        // Set a test value
        $session->set('test_key', 'test_value_' . time());
        
        // Get the value back
        $value = $session->get('test_key');
        
        return new Response('Session test: ' . $value);
    }
    
    #[Route('/test/auth-redirect', name: 'app_test_auth_redirect')]
    public function testAuthRedirect(): Response
    {
        if ($this->getUser()) {
            return new Response('Authenticated user: ' . $this->getUser()->getUserIdentifier());
        } else {
            return $this->redirectToRoute('app_login');
        }
    }
}