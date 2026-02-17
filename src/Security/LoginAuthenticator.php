<?php
// src/Security/LoginAuthenticator.php

namespace App\Security;

use App\Entity\User;
use App\Service\UserLastLoginService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private UserLastLoginService $userLastLoginService
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Перенаправляем на страницу входа
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Неверный email или пароль.');
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Аккаунт деактивирован.');
                }
                
                if ($user->isAccountLocked()) {
                    throw new CustomUserMessageAuthenticationException('Аккаунт заблокирован. Повторите попытку позже.');
                }

                // Обновляем время последнего входа асинхронно, чтобы не блокировать аутентификацию
                // Вызываем сервис для обновления времени последнего входа
                $this->userLastLoginService->updateUserLastLogin($user);

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Log successful authentication
        error_log('Authentication successful for user: ' . $token->getUser()->getUserIdentifier());
        
        // Перенаправляем на предыдущую страницу или на дашборд
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            error_log('Redirecting to target path: ' . $targetPath);
            return new RedirectResponse($targetPath);
        }

        // Явно указываем маршрут дашборда
        $dashboardUrl = $this->urlGenerator->generate('app_dashboard');
        error_log('Redirecting to dashboard: ' . $dashboardUrl);
        return new RedirectResponse($dashboardUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        error_log('Authentication failed: ' . $exception->getMessage());
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        
        // Handle failed login attempts and account locking
        $email = $request->request->get('email', '');
        if ($email) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $failedAttempts = $user->getFailedLoginAttempts() + 1;
                $user->setFailedLoginAttempts($failedAttempts);
                
                // Lock account after 5 failed attempts
                if ($failedAttempts >= 5) {
                    $lockedUntil = new \DateTime();
                    $lockedUntil->modify('+15 minutes'); // Lock for 15 minutes
                    $user->lockAccount($lockedUntil);
                    
                    error_log('Account locked for user: ' . $user->getEmail() . ' until ' . $lockedUntil->format('Y-m-d H:i:s'));
                }
                
                $this->entityManager->flush();
            }
        }
        
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }
}
