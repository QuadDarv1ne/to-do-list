<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        return $this->render('about/index.html.twig', [
            'system_name' => 'CRM система: Анализ продаж',
            'company_name' => 'ООО «Дальневосточный фермер»',
            'version' => '1.0.0',
            'features' => [
                'Управление задачами и проектами',
                'Аналитика продаж и отчетность',
                'Календарь и планирование',
                'Система уведомлений',
                'Управление пользователями',
                'Двухфакторная аутентификация',
                'Экспорт данных',
                'Мониторинг производительности',
            ],
        ]);
    }
}
