<?php

namespace App\Controller;

use App\Service\TwoFactorAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/2fa')]
class TwoFactorController extends AbstractController
{
    #[Route('/login', name: '2fa_login', methods: ['GET'])]
    public function login(): Response
    {
        // This route is used by the security system to display the 2FA form
        return $this->render('2fa/form.html.twig');
    }

    #[Route('/login_check', name: '2fa_login_check', methods: ['POST'])]
    public function loginCheck(): never
    {
        // This route is intercepted by the security system
        throw new \LogicException('This code should never be reached');
    }

    #[Route('/setup', name: 'app_2fa_setup', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function setup(TwoFactorAuthService $twoFactorAuthService): Response
    {
        $user = $this->getUser();

        // Check if 2FA is already enabled
        if ($twoFactorAuthService->isTwoFactorEnabled($user)) {
            return $this->redirectToRoute('app_2fa_status');
        }

        $setupData = $twoFactorAuthService->enableTwoFactor($user);

        return $this->render('2fa/setup.html.twig', [
            'secret' => $setupData['secret'],
            'qr_code_image' => $setupData['qr_code_image'],
            'qr_code_url' => $setupData['qr_code_url'],
        ]);
    }

    #[Route('/verify-setup', name: 'app_2fa_verify_setup', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function verifySetup(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
    ): JsonResponse {
        $user = $this->getUser();
        $code = $request->request->get('code');

        if (!$code) {
            return new JsonResponse(['success' => false, 'message' => 'Код обязателен'], 400);
        }

        $isValid = $twoFactorAuthService->verifyAndActivate($user, $code);

        if ($isValid) {
            // Generate backup codes
            $backupCodes = $twoFactorAuthService->generateBackupCodes($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Двухфакторная аутентификация успешно активирована',
                'backup_codes' => $backupCodes,
            ]);
        } else {
            return new JsonResponse([
                'success' => false,
                'message' => 'Неверный код. Попробуйте еще раз.',
            ], 400);
        }
    }

    #[Route('/status', name: 'app_2fa_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(TwoFactorAuthService $twoFactorAuthService): Response
    {
        $user = $this->getUser();
        $status = $twoFactorAuthService->getTwoFactorStatus($user);

        return $this->render('2fa/status.html.twig', [
            'status' => $status,
        ]);
    }

    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disable(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$twoFactorAuthService->isTwoFactorEnabled($user)) {
            return new JsonResponse(['success' => false, 'message' => '2FA не активирована'], 400);
        }

        $twoFactorAuthService->disableTwoFactor($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Двухфакторная аутентификация отключена',
        ]);
    }

    #[Route('/backup-codes', name: 'app_2fa_backup_codes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function showBackupCodes(TwoFactorAuthService $twoFactorAuthService): Response
    {
        $user = $this->getUser();

        if (!$twoFactorAuthService->isTwoFactorEnabled($user)) {
            throw $this->createNotFoundException('2FA не активирована');
        }

        $status = $twoFactorAuthService->getTwoFactorStatus($user);
        $backupCodes = $user->getBackupCodes() ?? [];

        return $this->render('2fa/backups.html.twig', [
            'backup_codes' => $backupCodes,
            'status' => $status,
        ]);
    }

    #[Route('/regenerate-backup-codes', name: 'app_2fa_regenerate_backup_codes', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function regenerateBackupCodes(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$twoFactorAuthService->isTwoFactorEnabled($user)) {
            return new JsonResponse(['success' => false, 'message' => '2FA не активирована'], 400);
        }

        $newCodes = $twoFactorAuthService->regenerateBackupCodes($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Резервные коды обновлены',
            'backup_codes' => $newCodes,
        ]);
    }

    #[Route('/api/verify', name: 'app_2fa_api_verify', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiVerify(
        Request $request,
        TwoFactorAuthService $twoFactorAuthService,
    ): JsonResponse {
        $user = $this->getUser();
        $code = $request->request->get('code');
        $type = $request->request->get('type', 'authenticator'); // authenticator or backup

        if (!$code) {
            return new JsonResponse(['success' => false, 'message' => 'Код обязателен'], 400);
        }

        $isValid = false;
        $message = '';

        if ($type === 'backup') {
            $isValid = $twoFactorAuthService->verifyBackupCode($user, $code);
            $message = $isValid ? 'Резервный код принят' : 'Неверный резервный код';
        } else {
            $isValid = $twoFactorAuthService->verifyCode($user, $code);
            $message = $isValid ? 'Код подтвержден' : 'Неверный код';
        }

        return new JsonResponse([
            'success' => $isValid,
            'message' => $message,
        ]);
    }

    #[Route('/recovery', name: 'app_2fa_recovery', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recoveryInfo(TwoFactorAuthService $twoFactorAuthService): Response
    {
        $user = $this->getUser();
        $status = $twoFactorAuthService->getTwoFactorStatus($user);

        return $this->render('2fa/recovery.html.twig', [
            'status' => $status,
        ]);
    }
}
