<?php

declare(strict_types=1);

namespace App\Admin\Controller;

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

use function is_string;

final class PasswordResetController extends AbstractController
{
    #[Route('/admin/forgot-password', name: 'app_admin_forgot_password', methods: ['GET', 'POST'])]
    public function requestReset(Request $request, PasswordResetService $passwordReset): Response
    {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $identifier = $form->get('identifier')->getData();
            $identifier = is_string($identifier) ? $identifier : '';
            $language = SupportedLanguage::tryFrom($request->getLocale()) ?? SupportedLanguage::Spanish;
            $result = $passwordReset->request(
                $identifier,
                PasswordResetChannel::Admin,
                $language,
                $request->getClientIp() ?? 'unknown',
            );

            if ($result->rateLimitedByIp) {
                $form->addError(new FormError('Demasiados intentos. Espera un momento y vuelve a intentarlo.'));
            } else {
                $this->addFlash('success', 'Si la cuenta existe y se puede recuperar, enviamos un correo con los pasos.');

                return $this->redirectToRoute('app_admin_forgot_password');
            }
        }

        return $this->render('@admin/security/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route(
        '/admin/reset-password/{token}',
        name: 'app_admin_reset_password',
        requirements: ['token' => '.+'],
        methods: ['GET', 'POST'],
    )]
    public function reset(
        Request $request,
        string $token,
        PasswordResetService $passwordReset,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $resetToken = $passwordReset->findOpen($token, PasswordResetChannel::Admin);

        if (!$resetToken instanceof PasswordResetToken) {
            return $this->render('@admin/security/reset_password.html.twig', [
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
                $this->addFlash('success', 'Tu contraseña se actualizó. Entra con la nueva.');

                return $this->redirectToRoute('app_admin_login');
            }

            return $this->render('@admin/security/reset_password.html.twig', [
                'can_reset' => false,
                'resetForm' => null,
            ]);
        }

        return $this->render('@admin/security/reset_password.html.twig', [
            'can_reset' => true,
            'resetForm' => $form->createView(),
        ]);
    }
}
