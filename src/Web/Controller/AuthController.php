<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Web\Form\LoginFormType;
use App\Web\Form\RegistrationFormType;
use App\Web\Registration\AccountAlreadyExistsException;
use App\Web\Registration\RegistrationData;
use App\Web\Service\PublicRegistrationService;
use App\Web\Service\SignupLegalCatalog;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthController extends AbstractController
{
    #[Route(
        '/{_locale}/login',
        name: 'app_public_login',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function login(
        AuthenticationUtils $authenticationUtils,
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
    ): Response {
        $redirect = $this->redirectAuthenticatedUser($settings, $languages);

        if ($redirect instanceof Response) {
            return $redirect;
        }

        $loginForm = $this->createForm(LoginFormType::class, [
            '_username' => $authenticationUtils->getLastUsername(),
        ]);

        return $this->render('@web/auth/login.html.twig', [
            'loginForm' => $loginForm->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(
        '/{_locale}/logout',
        name: 'app_public_logout',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
    )]
    public function logout(): never
    {
        throw new LogicException('Symfony intercepts this route.');
    }

    #[Route(
        '/{_locale}/register',
        name: 'app_public_register',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function register(
        Request $request,
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
        SignupLegalCatalog $legalCatalog,
        PublicRegistrationService $registration,
        Security $security,
        TranslatorInterface $translator,
        #[Autowire(service: 'limiter.public_registration')]
        RateLimiterFactory $registrationLimiter,
    ): Response {
        $redirect = $this->redirectAuthenticatedUser($settings, $languages);

        if ($redirect instanceof Response) {
            return $redirect;
        }

        $language = SupportedLanguage::tryFrom($request->getLocale()) ?? SupportedLanguage::Spanish;
        $documents = $legalCatalog->available($language);
        $form = $this->createForm(RegistrationFormType::class, new RegistrationData(), [
            'require_legal_acceptance' => [] !== $documents,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $limit = $registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume();

            if (!$limit->isAccepted()) {
                $form->addError(new FormError($translator->trans('auth.register_rate_limited')));
            } elseif ($form->isValid()) {
                /** @var RegistrationData $data */
                $data = $form->getData();

                try {
                    $result = $registration->register(
                        $data,
                        $language,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                    );
                } catch (AccountAlreadyExistsException) {
                    $form->get('email')->addError(new FormError($translator->trans('auth.register_unavailable')));
                    $result = null;
                }

                if (null !== $result) {
                    if (!$result->emailSent) {
                        $this->addFlash('error', 'auth.confirm_mail_failed');
                    }

                    $response = $security->login($result->user, firewallName: 'public');

                    if ($response instanceof Response) {
                        return $response;
                    }

                    return $this->redirectToRoute('app_public_account_confirm', [
                        '_locale' => $language->value,
                    ]);
                }
            }
        }

        return $this->render('@web/auth/register.html.twig', [
            'registrationForm' => $form->createView(),
            'legal_documents' => $documents,
        ]);
    }

    private function redirectAuthenticatedUser(
        GeneralSettingsProvider $settings,
        LegalLanguageResolver $languages,
    ): ?Response {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return null;
        }

        $route = match ($user->getStatus()) {
            UserStatus::UnconfirmedAccount => 'app_public_account_confirm',
            UserStatus::UncompleteProfileInfo => 'app_public_profile',
            default => 'app_public_home',
        };

        return $this->redirectToRoute($route, [
            '_locale' => $languages->preferredForUser($user, $settings->get())->value,
        ]);
    }
}
