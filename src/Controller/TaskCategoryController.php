<?php

namespace App\Controller;

use App\Entity\TaskCategory;
use App\Form\TaskCategoryType;
use App\Repository\TaskCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-category')]
#[IsGranted('ROLE_USER')]
class TaskCategoryController extends AbstractController
{
    #[Route('/', name: 'app_task_category_index', methods: ['GET'])]
    public function index(TaskCategoryRepository $taskCategoryRepository): Response
    {
        $categories = $taskCategoryRepository->findByUser($this->getUser());
        
        return $this->render('task_category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'app_task_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $taskCategory = new TaskCategory();
        $taskCategory->setUser($this->getUser());
        
        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskCategory);
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно создана');

            return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_category/form.html.twig', [
            'task_category' => $taskCategory,
            'form' => $form,
            'title' => 'Создать категорию',
        ]);
    }

    #[Route('/{id}', name: 'app_task_category_show', methods: ['GET'])]
    public function show(TaskCategory $taskCategory): Response
    {
        $this->denyAccessUnlessGranted('TASK_CATEGORY_VIEW', $taskCategory);
        
        return $this->render('task_category/show.html.twig', [
            'task_category' => $taskCategory,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskCategory $taskCategory, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('TASK_CATEGORY_EDIT', $taskCategory);
        
        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно обновлена');

            return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_category/form.html.twig', [
            'task_category' => $taskCategory,
            'form' => $form,
            'title' => 'Редактировать категорию',
        ]);
    }

    #[Route('/{id}', name: 'app_task_category_delete', methods: ['POST'])]
    public function delete(Request $request, TaskCategory $taskCategory, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('TASK_CATEGORY_DELETE', $taskCategory);
        
        if ($this->isCsrfTokenValid('delete'.$taskCategory->getId(), $request->request->get('_token'))) {
            // Check if category has tasks
            if (count($taskCategory->getTasks()) > 0) {
                $this->addFlash('error', 'Нельзя удалить категорию, содержащую задачи');
                return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
            }
            
            $entityManager->remove($taskCategory);
            $entityManager->flush();
            
            $this->addFlash('success', 'Категория успешно удалена');
        }

        return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
    }
}