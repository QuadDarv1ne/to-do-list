<?php

namespace App\Controller;

use App\Controller\Traits\FlashMessageTrait;
use App\Form\ChangePasswordType;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    use FlashMessageTrait;

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('profile_controller_change_password');
        }

        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Verify current password
            $currentPassword = $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->flashError('Неверный текущий пароль');

                try {
                    return $this->redirectToRoute('app_profile_change_password');
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTiming('profile_controller_change_password');
                    }
                }
            }

            $plainPassword = $form->get('plainPassword')->getData();

            // Check if new password is different from current
            if ($passwordHasher->isPasswordValid($user, $plainPassword)) {
                $this->flashError('Новый пароль должен отличаться от текущего');

                try {
                    return $this->redirectToRoute('app_profile_change_password');
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTiming('profile_controller_change_password');
                    }
                }
            }

            // Set new password
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->setPasswordChangedAt(new \DateTimeImmutable());

            $entityManager->flush();

            $this->flashSuccess('Пароль успешно изменен');

            try {
                return $this->redirectToRoute('app_profile_show');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('profile_controller_change_password');
                }
            }
        }

        try {
            return $this->render('profile/change_password.html.twig', [
                'form' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('profile_controller_change_password');
            }
        }
    }

    #[Route('/', name: 'app_profile_show', methods: ['GET'])]
    public function show(?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('profile_controller_show');
        }

        $user = $this->getUser();

        try {
            return $this->render('profile/show.html.twig', [
                'user' => $user,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('profile_controller_show');
            }
        }
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('profile_controller_edit');
        }

        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        $form = $this->createForm(\App\Form\UserType::class, $user, ['is_new' => false, 'is_profile_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email is being changed and if it's already taken by another user
            $newEmail = $user->getEmail();
            if ($originalEmail !== $newEmail) {
                $existingUser = $entityManager->getRepository(\App\Entity\User::class)
                    ->findOneBy(['email' => $newEmail]);

                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->flashError('Пользователь с таким email уже существует');

                    try {
                        return $this->redirectToRoute('app_profile_edit');
                    } finally {
                        if ($performanceMonitor) {
                            $performanceMonitor->stopTiming('profile_controller_edit');
                        }
                    }
                }
            }

            $entityManager->flush();

            $this->flashUpdated('Профиль успешно обновлен');

            try {
                return $this->redirectToRoute('app_profile_show');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('profile_controller_edit');
                }
            }
        }

        try {
            return $this->render('profile/edit.html.twig', [
                'user' => $user,
                'form' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('profile_controller_edit');
            }
        }
    }
}
