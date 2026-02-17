<?php
// src/Controller/DebugController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DebugController extends AbstractController
{
    #[Route('/debug/auth', name: 'app_debug_auth')]
    public function debugAuth(): Response
    {
        $user = $this->getUser();
        
        if ($user instanceof UserInterface) {
            return new Response('Authenticated as: ' . $user->getUserIdentifier() . ' (' . get_class($user) . ')');
        } else {
            return new Response('Not authenticated');
        }
    }
    
    #[Route('/debug/test-login', name: 'app_debug_test_login')]
    public function testLogin(): Response
    {
        return $this->render('debug/test_login.html.twig');
    }
}
