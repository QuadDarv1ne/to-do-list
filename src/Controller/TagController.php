<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagType;
use App\Repository\TagRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tag')]
class TagController extends AbstractController
{
    #[Route('/', name: 'app_tag_index', methods: ['GET'])]
    public function index(
        TagRepository $tagRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_index');
        }
        
        try {
            return $this->render('tag/index.html.twig', [
                'tags' => $tagRepository->findAll(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_index');
            }
        }
    }

    #[Route('/new', name: 'app_tag_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_new');
        }
        
        $tag = new Tag();
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tag);
            $entityManager->flush();

            $this->addFlash('success', 'Тег успешно создан.');

            try {
                return $this->redirectToRoute('app_tag_index', [], Response::HTTP_SEE_OTHER);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('tag_controller_new');
                }
            }
        }

        try {
            return $this->render('tag/new.html.twig', [
                'tag' => $tag,
                'form' => $form,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_new');
            }
        }
    }

    #[Route('/{id}', name: 'app_tag_show', methods: ['GET'])]
    public function show(
        Tag $tag,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_show');
        }
        
        try {
            return $this->render('tag/show.html.twig', [
                'tag' => $tag,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_show');
            }
        }
    }

    #[Route('/{id}/edit', name: 'app_tag_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Tag $tag, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_edit');
        }
        
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tag->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Тег успешно обновлен.');

            try {
                return $this->redirectToRoute('app_tag_index', [], Response::HTTP_SEE_OTHER);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('tag_controller_edit');
                }
            }
        }

        try {
            return $this->render('tag/edit.html.twig', [
                'tag' => $tag,
                'form' => $form,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_edit');
            }
        }
    }

    #[Route('/{id}', name: 'app_tag_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Tag $tag, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_delete');
        }
        
        if ($this->isCsrfTokenValid('delete'.$tag->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tag);
            $entityManager->flush();

            $this->addFlash('success', 'Тег успешно удален.');
        }

        try {
            return $this->redirectToRoute('app_tag_index', [], Response::HTTP_SEE_OTHER);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_delete');
            }
        }
    }
    
    #[Route('/create-ajax', name: 'app_tag_create_ajax', methods: ['POST'])]
    public function createAjax(
        Request $request, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('tag_controller_create_ajax');
        }
        
        $data = json_decode($request->getContent(), true);
        $tagName = trim($data['name'] ?? '');
        $tagColor = $data['color'] ?? '#007bff';
        $tagDescription = $data['description'] ?? '';

        if (empty($tagName)) {
            try {
                return $this->json([
                    'success' => false,
                    'message' => 'Имя тега не может быть пустым'
                ], 400);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('tag_controller_create_ajax');
                }
            }
        }

        // Check if tag already exists
        $existingTag = $entityManager->getRepository(Tag::class)
            ->findOneBy(['name' => $tagName]);

        if ($existingTag) {
            try {
                return $this->json([
                    'success' => false,
                    'message' => 'Тег с таким именем уже существует',
                    'tag' => [
                        'id' => $existingTag->getId(),
                        'name' => $existingTag->getName(),
                        'color' => $existingTag->getColor()
                    ]
                ]);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('tag_controller_create_ajax');
                }
            }
        }

        $tag = new Tag();
        $tag->setName($tagName);
        $tag->setDescription($tagDescription);
        $tag->setColor($tagColor);

        $entityManager->persist($tag);
        $entityManager->flush();

        try {
            return $this->json([
                'success' => true,
                'message' => 'Тег успешно создан',
                'tag' => [
                    'id' => $tag->getId(),
                    'name' => $tag->getName(),
                    'color' => $tag->getColor(),
                    'description' => $tag->getDescription()
                ]
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('tag_controller_create_ajax');
            }
        }
    }
}