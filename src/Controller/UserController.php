<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_index');
        }
        
        try {
            return $this->render('user/index.html.twig', [
                'users' => $userRepository->findAll(),
                'statistics' => $userRepository->getStatistics(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_index');
            }
        }
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_new');
        }
        
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Хешируем пароль
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Пользователь успешно создан');

            try {
                return $this->redirectToRoute('app_user_index');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('user_controller_new');
                }
            }
        }

        try {
            return $this->render('user/new.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_new');
            }
        }
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(
        User $user,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_show');
        }
        
        try {
            return $this->render('user/show.html.twig', [
                'user' => $user,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_show');
            }
        }
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_edit');
        }
        
        $form = $this->createForm(UserType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Если введен новый пароль, хешируем его
            if ($form->has('plainPassword')) {
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Пользователь успешно обновлен');

            try {
                return $this->redirectToRoute('app_user_index');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('user_controller_edit');
                }
            }
        }

        try {
            return $this->render('user/edit.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_edit');
            }
        }
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_delete');
        }
        
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            // Не удаляем администратора системы
            if ($user->getUsername() === 'admin') {
                $this->addFlash('error', 'Нельзя удалить системного администратора');
                
                try {
                    return $this->redirectToRoute('app_user_index');
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTimer('user_controller_delete');
                    }
                }
            }

            $entityManager->remove($user);
            $entityManager->flush();
            
            $this->addFlash('success', 'Пользователь успешно удален');
        }

        try {
            return $this->redirectToRoute('app_user_index');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_delete');
            }
        }
    }

    #[Route('/{id}/toggle-active', name: 'app_user_toggle_active', methods: ['POST'])]
    public function toggleActive(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_toggle_active');
        }
        
        if ($this->isCsrfTokenValid('toggle-active'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(!$user->isActive());
            $entityManager->flush();

            $status = $user->isActive() ? 'активирован' : 'деактивирован';
            $this->addFlash('success', "Пользователь {$status}");
        }

        try {
            return $this->redirectToRoute('app_user_index');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_toggle_active');
            }
        }
    }

    #[Route('/{id}/unlock', name: 'app_user_unlock', methods: ['POST'])]
    public function unlock(
        Request $request,
        User $user,
        UserRepository $userRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('user_controller_unlock');
        }
        
        if ($this->isCsrfTokenValid('unlock'.$user->getId(), $request->request->get('_token'))) {
            $userRepository->unlockUser($user);
            $this->addFlash('success', 'Пользователь разблокирован');
        }

        try {
            return $this->redirectToRoute('app_user_index');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('user_controller_unlock');
            }
        }
    }
}