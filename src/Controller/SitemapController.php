<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use App\Repository\KnowledgeBaseArticleRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Контроллер для генерации XML sitemap
 */
class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(
        EntityManagerInterface $em,
        TaskRepository $taskRepository,
        ClientRepository $clientRepository,
        DealRepository $dealRepository,
        KnowledgeBaseArticleRepository $articleRepository,
    ): Response {
        $baseUrl = 'https://crm.dvfarm.ru';

        // Базовые страницы
        $urls = [
            [
                'loc' => $baseUrl,
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => $baseUrl . '/dashboard',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => $baseUrl . '/tasks',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => $baseUrl . '/calendar',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl . '/kanban',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl . '/clients',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl . '/deals',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl . '/reports',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'loc' => $baseUrl . '/analytics',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'loc' => $baseUrl . '/knowledge-base',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
            [
                'loc' => $baseUrl . '/login',
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
        ];

        // Добавляем публичные статьи базы знаний
        $articles = $articleRepository->findBy(['status' => 'published'], ['updatedAt' => 'DESC'], 100);
        foreach ($articles as $article) {
            if ($article->getUpdatedAt()) {
                $lastmod = $article->getUpdatedAt()->format('Y-m-d');
            } else {
                $lastmod = $article->getCreatedAt()->format('Y-m-d');
            }

            $urls[] = [
                'loc' => $baseUrl . '/knowledge-base/article/' . $article->getId(),
                'lastmod' => $lastmod,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        // Генерируем XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        foreach ($urls as $url) {
            $urlNode = $xml->addChild('url');
            $urlNode->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1));

            if (isset($url['lastmod'])) {
                $urlNode->addChild('lastmod', $url['lastmod']);
            }

            if (isset($url['changefreq'])) {
                $urlNode->addChild('changefreq', $url['changefreq']);
            }

            if (isset($url['priority'])) {
                $urlNode->addChild('priority', $url['priority']);
            }
        }

        $response = new Response($xml->asXML(), 200, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);

        return $response;
    }
}
