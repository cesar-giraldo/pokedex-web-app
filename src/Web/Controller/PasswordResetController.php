<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\PasswordResetChannel;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\PasswordResetToken;
use App\Security\Form\ForgotPasswordRequestType;
use App\Security\Form\ResetPasswordType;
use App\Security\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

final class PasswordResetController extends AbstractController
{
    #[Route(
        '/{_locale}/forgot-password',
        name: 'app_public_forgot_password',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function requestReset(
        Request $request,
        PasswordResetService $passwordReset,
        TranslatorInterface $translator,
    ): Response {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $identifier = $form->get('identifier')->getData();
            $identifier = is_string($identifier) ? $identifier : '';
            $language = SupportedLanguage::tryFrom($request->getLocale()) ?? SupportedLanguage::Spanish;
            $result = $passwordReset->request(
                $identifier,
                PasswordResetChannel::Web,
                $language,
                $request->getClientIp() ?? 'unknown',
            );

            if ($result->rateLimitedByIp) {
                $form->addError(new FormError($translator->trans('auth.forgot_password_rate_limited')));
            } else {
                $this->addFlash('success', 'auth.forgot_password_sent');

                return $this->redirectToRoute('app_public_forgot_password', [
                    '_locale' => $request->getLocale(),
                ]);
            }
        }

        return $this->render('@web/auth/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route(
        '/{_locale}/reset-password/{token}',
        name: 'app_public_reset_password',
        requirements: [
            '_locale' => SupportedLanguage::ROUTE_REQUIREMENT,
            'token' => '.+',
        ],
        methods: ['GET', 'POST'],
    )]
    public function reset(
        Request $request,
        string $token,
        PasswordResetService $passwordReset,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $resetToken = $passwordReset->findOpen($token, PasswordResetChannel::Web);

        if (!$resetToken instanceof PasswordResetToken) {
            return $this->render('@web/auth/reset_password.html.twig', [
                'can_reset' => false,
                'resetForm' => null,
            ]);
        }

        $form = $this->createForm(ResetPasswordType::class, null, [
            'user' => $resetToken->getUser(),
            'password_hasher' => $passwordHasher,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $plainPassword = is_string($plainPassword) ? $plainPassword : '';
            $language = SupportedLanguage::tryFrom($request->getLocale()) ?? SupportedLanguage::Spanish;

            if ($passwordReset->complete($resetToken, $plainPassword, $language)) {
                $tokenStorage->setToken(null);
                $this->addFlash('success', 'auth.reset_password_success');

                return $this->redirectToRoute('app_public_login', [
                    '_locale' => $request->getLocale(),
                ]);
            }

            return $this->render('@web/auth/reset_password.html.twig', [
                'can_reset' => false,
                'resetForm' => null,
            ]);
        }

        return $this->render('@web/auth/reset_password.html.twig', [
            'can_reset' => true,
            'resetForm' => $form->createView(),
        ]);
    }
}
