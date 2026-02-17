<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private TokenGeneratorInterface $tokenGenerator,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('reset_password_controller_request');
        }
        
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                return $this->processSendingPasswordResetEmail(
                    $form->get('email')->getData(),
                    $performanceMonitor
                );
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('reset_password_controller_request');
                }
            }
        }

        try {
            return $this->render('reset_password/request.html.twig', [
                'requestForm' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('reset_password_controller_request');
            }
        }
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('reset_password_controller_check_email');
        }
        
        try {
            // We prevent users from directly accessing this page
            return $this->render('reset_password/check_email.html.twig');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('reset_password_controller_check_email');
            }
        }
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, ?string $token = null, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('reset_password_controller_reset');
        }
        
        if ($token === null) {
            try {
                throw $this->redirectToRoute('app_forgot_password_request');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('reset_password_controller_reset');
                }
            }
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetPasswordToken' => $token]);

        if ($user === null) {
            // TODO: You could redirect to a standard page not found but that is a bit better UX
            try {
                throw $this->redirectToRoute('app_forgot_password_request');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('reset_password_controller_reset');
                }
            }
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $user->setResetPasswordToken(null);
            $user->setResetPasswordRequestedAt(null);

            // Encode the plain password, and set it.
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();

            // Log the user
            try {
                return $this->redirectToRoute('app_login');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('reset_password_controller_reset');
                }
            }
        }

        try {
            return $this->render('reset_password/reset.html.twig', [
                'resetForm' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('reset_password_controller_reset');
            }
        }
    }

    private function processSendingPasswordResetEmail(string $emailFormData, ?PerformanceMonitorService $performanceMonitor = null): RedirectResponse
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('reset_password_controller_process_sending_email');
        }
        
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            try {
                return $this->redirectToRoute('app_check_email');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTiming('reset_password_controller_process_sending_email');
                }
            }
        }

        // Generate a signed url and save it in the DB
        $resetToken = $this->tokenGenerator->generateToken();
        $user->setResetPasswordToken($resetToken);
        $user->setResetPasswordRequestedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Create and send email
        $email = (new TemplatedEmail())
            ->from(new Address('noreply@todolist.local', 'Система управления задачами'))
            ->to($user->getEmail())
            ->subject('Запрос на сброс пароля')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user' => $user,
            ])
        ;

        $this->mailer->send($email);

        try {
            return $this->redirectToRoute('app_check_email');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('reset_password_controller_process_sending_email');
            }
        }
    }
}
