<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface as GoogleTwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorAuthService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private ?GoogleAuthenticatorInterface $googleAuthenticator;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ?GoogleAuthenticatorInterface $googleAuthenticator = null
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->googleAuthenticator = $googleAuthenticator;
    }

    /**
     * Enable 2FA for user
     */
    public function enableTwoFactor(User $user): array
    {
        if (!$this->googleAuthenticator) {
            throw new \RuntimeException('Google Authenticator is not configured');
        }

        // Generate secret key
        $secret = $this->googleAuthenticator->generateSecret();
        
        // Create QR code URL
        $qrCodeUrl = $this->googleAuthenticator->getQRContent($user);
        
        // Generate QR code image
        $qrCodeImage = $this->generateQrCode($qrCodeUrl);

        // Store secret temporarily (will be saved after verification)
        $user->setTotpSecretTemp($secret);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->logger->info("2FA enabled initiated for user {$user->getId()}");

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_image' => $qrCodeImage
        ];
    }

    /**
     * Verify 2FA code and activate 2FA
     */
    public function verifyAndActivate(User $user, string $code): bool
    {
        if (!$this->googleAuthenticator) {
            return false;
        }

        $tempSecret = $user->getTotpSecretTemp();
        if (!$tempSecret) {
            return false;
        }

        // Temporarily set the secret to verify the code
        $originalSecret = $user->getTotpSecret();
        $user->setTotpSecret($tempSecret);
        
        $isValid = $this->googleAuthenticator->checkCode($user, $code);
        
        if ($isValid) {
            // Activate 2FA permanently
            $user->setTotpSecret($tempSecret);
            $user->setTotpSecretTemp(null);
            $user->setIsTotpEnabled(true);
            $user->setTotpEnabledAt(new \DateTime());
            $this->entityManager->flush();
            
            $this->logger->info("2FA activated for user {$user->getId()}");
        } else {
            // Restore original secret
            $user->setTotpSecret($originalSecret);
            $user->setTotpSecretTemp(null);
        }

        return $isValid;
    }

    /**
     * Disable 2FA for user
     */
    public function disableTwoFactor(User $user): void
    {
        $user->setTotpSecret(null);
        $user->setTotpSecretTemp(null);
        $user->setIsTotpEnabled(false);
        $user->setTotpEnabledAt(null);
        
        $this->entityManager->flush();
        
        $this->logger->info("2FA disabled for user {$user->getId()}");
    }

    /**
     * Verify 2FA code for login
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (!$this->googleAuthenticator || !$user->isTotpEnabled()) {
            return false;
        }

        $isValid = $this->googleAuthenticator->checkCode($user, $code);
        
        if ($isValid) {
            $this->logger->info("2FA code verified for user {$user->getId()}");
        } else {
            $this->logger->warning("Invalid 2FA code attempt for user {$user->getId()}");
        }

        return $isValid;
    }

    /**
     * Generate backup codes for user
     */
    public function generateBackupCodes(User $user, int $count = 10): array
    {
        $backupCodes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8-character codes
            $backupCodes[] = $code;
        }

        $user->setBackupCodes($backupCodes);
        $this->entityManager->flush();

        $this->logger->info("Generated {$count} backup codes for user {$user->getId()}");

        return $backupCodes;
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        
        if (!$backupCodes) {
            return false;
        }

        $code = strtoupper(trim($code));
        $key = array_search($code, $backupCodes);

        if ($key !== false) {
            // Remove used backup code
            unset($backupCodes[$key]);
            $user->setBackupCodes(array_values($backupCodes));
            $this->entityManager->flush();
            
            $this->logger->info("Backup code used by user {$user->getId()}");
            return true;
        }

        return false;
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isTwoFactorEnabled(User $user): bool
    {
        return $user->isTotpEnabled();
    }

    /**
     * Get 2FA status for user
     */
    public function getTwoFactorStatus(User $user): array
    {
        return [
            'enabled' => $user->isTotpEnabled(),
            'enabled_at' => $user->getTotpEnabledAt()?->format('c'),
            'has_backup_codes' => !empty($user->getBackupCodes()),
            'backup_codes_count' => count($user->getBackupCodes() ?? [])
        ];
    }

    /**
     * Generate QR code image
     */
    private function generateQrCode(string $content): string
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->data($content)
                ->build();

            return 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (\Exception $e) {
            $this->logger->error("Failed to generate QR code: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(User $user): array
    {
        return $this->generateBackupCodes($user);
    }

    /**
     * Validate 2FA setup requirements
     */
    public function validateSetupRequirements(User $user): array
    {
        $errors = [];
        
        // Check if user already has 2FA enabled
        if ($user->isTotpEnabled()) {
            $errors[] = '2FA уже активирована для этого аккаунта';
        }
        
        // Check if Google Authenticator is available
        if (!$this->googleAuthenticator) {
            $errors[] = 'Сервис аутентификации недоступен';
        }
        
        return $errors;
    }
}
