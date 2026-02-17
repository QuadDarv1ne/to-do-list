<?php

namespace App\Controller;

use App\Entity\TaskCategory;
use App\Form\TaskCategoryType;
use App\Repository\TaskCategoryRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-category')]
#[IsGranted('ROLE_USER')]
class TaskCategoryController extends AbstractController
{
    #[Route('/', name: 'app_task_category_index', methods: ['GET'])]
    public function index(
        TaskCategoryRepository $taskCategoryRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_category_controller_index');
        }
        
        $categories = $taskCategoryRepository->findByUser($this->getUser());
        
        try {
            return $this->render('task_category/index.html.twig', [
                'categories' => $categories,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_category_controller_index');
            }
        }
    }

    #[Route('/new', name: 'app_task_category_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_category_controller_new');
        }
        
        $taskCategory = new TaskCategory();
        $taskCategory->setUser($this->getUser());
        
        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskCategory);
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно создана');

            try {
                return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_category_controller_new');
                }
            }
        }

        try {
            return $this->render('task_category/form.html.twig', [
                'task_category' => $taskCategory,
                'form' => $form,
                'title' => 'Создать категорию',
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_category_controller_new');
            }
        }
    }

    #[Route('/{id}', name: 'app_task_category_show', methods: ['GET'])]
    public function show(
        TaskCategory $taskCategory,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_category_controller_show');
        }
        
        $this->denyAccessUnlessGranted('TASK_CATEGORY_VIEW', $taskCategory);
        
        try {
            return $this->render('task_category/show.html.twig', [
                'task_category' => $taskCategory,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_category_controller_show');
            }
        }
    }

    #[Route('/{id}/edit', name: 'app_task_category_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        TaskCategory $taskCategory, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_category_controller_edit');
        }
        
        $this->denyAccessUnlessGranted('TASK_CATEGORY_EDIT', $taskCategory);
        
        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно обновлена');

            try {
                return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('task_category_controller_edit');
                }
            }
        }

        try {
            return $this->render('task_category/form.html.twig', [
                'task_category' => $taskCategory,
                'form' => $form,
                'title' => 'Редактировать категорию',
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_category_controller_edit');
            }
        }
    }

    #[Route('/{id}', name: 'app_task_category_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        TaskCategory $taskCategory, 
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('task_category_controller_delete');
        }
        
        $this->denyAccessUnlessGranted('TASK_CATEGORY_DELETE', $taskCategory);
        
        if ($this->isCsrfTokenValid('delete'.$taskCategory->getId(), $request->request->get('_token'))) {
            // Check if category has tasks
            if (count($taskCategory->getTasks()) > 0) {
                $this->addFlash('error', 'Нельзя удалить категорию, содержащую задачи');
                
                try {
                    return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTiming('task_category_controller_delete');
                    }
                }
            }
            
            $entityManager->remove($taskCategory);
            $entityManager->flush();
            
            $this->addFlash('success', 'Категория успешно удалена');
        }

        try {
            return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('task_category_controller_delete');
            }
        }
    }
}
