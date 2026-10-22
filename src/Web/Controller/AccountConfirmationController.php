<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Web\Form\ConfirmationCodeFormType;
use App\Web\Registration\ConfirmationAttempt;
use App\Web\Registration\ConfirmationCodeData;
use App\Web\Service\AccountConfirmationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccountConfirmationController extends AbstractController
{
    #[Route(
        '/{_locale}/account/confirm',
        name: 'app_public_account_confirm',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function confirm(
        Request $request,
        AccountConfirmationService $confirmations,
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
        TranslatorInterface $translator,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return $this->redirectToRoute('app_public_login', ['_locale' => $request->getLocale()]);
        }

        $redirect = $this->redirectUnlessUnconfirmed($user, $settings, $languages);

        if ($redirect instanceof Response) {
            return $redirect;
        }

        $form = $this->createForm(ConfirmationCodeFormType::class, new ConfirmationCodeData());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ConfirmationCodeData $data */
            $data = $form->getData();
            $attempt = $confirmations->verify($user, $data->code());

            if (ConfirmationAttempt::Confirmed === $attempt) {
                $this->addFlash('account_confirmed', 'account.confirmed_complete_profile');

                return $this->redirectToRoute('app_public_profile', [
                    '_locale' => $languages->preferredForUser($user, $settings->get())->value,
                ]);
            }

            $form->addError(new FormError($translator->trans($this->messageKey($attempt))));
        }

        return $this->render('@web/auth/confirm_account.html.twig', [
            'confirmationForm' => $form->createView(),
            'email' => $user->getEmail() ?? $user->getNickname(),
        ]);
    }

    #[Route(
        '/{_locale}/account/confirm/resend',
        name: 'app_public_account_confirm_resend',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['POST'],
    )]
    public function resend(
        Request $request,
        AccountConfirmationService $confirmations,
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return $this->redirectToRoute('app_public_login', ['_locale' => $request->getLocale()]);
        }

        $redirect = $this->redirectUnlessUnconfirmed($user, $settings, $languages);

        if ($redirect instanceof Response) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('account_confirm_resend', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'auth.confirm_resend_invalid');

            return $this->redirectToRoute('app_public_account_confirm', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $attempt = $confirmations->resend($user);
        $bag = ConfirmationAttempt::Resent === $attempt ? 'success' : 'error';
        $this->addFlash($bag, $this->messageKey($attempt));

        return $this->redirectToRoute('app_public_account_confirm', [
            '_locale' => $languages->preferredForUser($user, $settings->get())->value,
        ]);
    }

    private function redirectUnlessUnconfirmed(
        User $user,
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
    ): ?Response {
        if (UserStatus::UnconfirmedAccount === $user->getStatus()) {
            return null;
        }

        $locale = $languages->preferredForUser($user, $settings->get())->value;
        $route = UserStatus::UncompleteProfileInfo === $user->getStatus()
            ? 'app_public_profile'
            : 'app_public_home';

        return $this->redirectToRoute($route, ['_locale' => $locale]);
    }

    private function messageKey(ConfirmationAttempt $attempt): string
    {
        return match ($attempt) {
            ConfirmationAttempt::Mismatch => 'auth.confirm_mismatch',
            ConfirmationAttempt::Expired => 'auth.confirm_expired',
            ConfirmationAttempt::Exhausted => 'auth.confirm_exhausted',
            ConfirmationAttempt::Missing => 'auth.confirm_missing',
            ConfirmationAttempt::Resent => 'auth.confirm_resent',
            ConfirmationAttempt::Cooldown => 'auth.confirm_cooldown',
            ConfirmationAttempt::Limited => 'auth.confirm_limited',
            ConfirmationAttempt::MailFailed => 'auth.confirm_mail_failed',
            ConfirmationAttempt::Confirmed => 'auth.confirm_resent',
        };
    }
}
