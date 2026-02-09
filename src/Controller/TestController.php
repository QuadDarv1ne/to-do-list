<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        // Test route generation
        $taskCategoryUrl = $this->generateUrl('app_task_category_index');
        return new Response('Task Category URL: ' . $taskCategoryUrl);
    }
}
