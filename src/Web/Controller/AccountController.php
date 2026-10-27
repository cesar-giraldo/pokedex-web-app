<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Data\IanaTimezones;
use App\Admin\Data\WorldCountryCodes;
use App\Admin\Form\UserProfileInfoType;
use App\Admin\Form\UserProfilePasswordType;
use App\Admin\Service\GeneralSettingsProvider;
use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\UserProfileImageFormHandler;
use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Admin\Service\Storage\UserProfileImageUploadException;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Notification\Email\WelcomeMailer;
use App\Web\Service\PendingFavoriteReturn;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function fclose;
use function fopen;
use function is_string;
use function stream_copy_to_stream;

#[IsGranted('PUBLIC_ACCOUNT')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserProfileImageFormHandler $profileImageFormHandler,
        private readonly UserProfileImageStorage $profileImageStorage,
        private readonly GeneralSettingsProvider $generalSettingsProvider,
        private readonly LegalLanguageResolver $legalLanguageResolver,
        private readonly WelcomeMailer $welcomeMailer,
        private readonly PendingFavoriteReturn $pendingFavoriteReturn,
    ) {
    }

    #[Route(
        '/{_locale}/profile',
        name: 'app_public_profile',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $settings = $this->generalSettingsProvider->get();
        $infoForm = $this->createForm(UserProfileInfoType::class, $user, [
            'country_choice_key' => WorldCountryCodes::DEFAULT_CHOICE_KEY,
            'nickname_editable' => false,
            'enabled_languages' => $settings->getEnabledLanguages(),
            'website_default_language' => $settings->getWebsiteDefaultLanguage(),
        ]);
        $passwordForm = $this->createForm(UserProfilePasswordType::class, null, [
            'user' => $user,
            'password_hasher' => $this->passwordHasher,
            'require_current_password' => $user->hasChosenPassword(),
        ]);

        $infoForm->handleRequest($request);
        $passwordForm->handleRequest($request);
        $locale = $request->getLocale();

        if ($infoForm->isSubmitted()) {
            return $this->handleInfoForm($request, $user, $infoForm, $passwordForm);
        }

        if ($passwordForm->isSubmitted()) {
            return $this->handlePasswordForm($user, $infoForm, $passwordForm, $locale);
        }

        return $this->renderProfile($user, $infoForm, $passwordForm);
    }

    #[Route(
        '/{_locale}/profile/avatar',
        name: 'app_public_profile_avatar',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        defaults: ['variant' => 'avatar'],
        methods: ['GET'],
    )]
    #[Route(
        '/{_locale}/profile/image/{variant}',
        name: 'app_public_profile_image',
        requirements: [
            '_locale' => SupportedLanguage::ROUTE_REQUIREMENT,
            'variant' => 'avatar|display|original',
        ],
        methods: ['GET'],
    )]
    public function avatar(string $variant): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profileImagePath = $user->getProfileImagePath();

        if (null === $profileImagePath || '' === $profileImagePath) {
            throw new NotFoundHttpException();
        }

        $imageVariant = ImageVariant::tryFrom($variant);
        if (!$imageVariant instanceof ImageVariant || !$imageVariant->isAllowedForProfile()) {
            throw new NotFoundHttpException();
        }

        try {
            $stream = $this->profileImageStorage->readStream($profileImagePath, $imageVariant);
        } catch (Throwable) {
            throw new NotFoundHttpException();
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            $output = fopen('php://output', 'w');

            if (false === $output) {
                return;
            }

            stream_copy_to_stream($stream, $output);
            fclose($stream);
            fclose($output);
        });

        $response->headers->set(
            'Content-Type',
            $this->profileImageStorage->resolveMimeType($profileImagePath, $imageVariant),
        );
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function languageOptions(): array
    {
        $options = [];

        foreach (SupportedLanguage::fromStoredValues($this->generalSettingsProvider->get()->getEnabledLanguages()) as $language) {
            $options[$language->value] = $language->label();
        }

        return $options;
    }

    /**
     * @param FormInterface<User> $infoForm
     * @param FormInterface<null> $passwordForm
     */
    private function handleInfoForm(Request $request, User $user, FormInterface $infoForm, FormInterface $passwordForm): Response
    {
        if (!$infoForm->isValid()) {
            $this->addFlash('error', 'account.review_fields');

            return $this->renderProfile($user, $infoForm, $passwordForm);
        }

        $profileCompleted = UserStatus::UncompleteProfileInfo === $user->getStatus()
            && $user->hasCompleteProfileContactInfo();

        if ($profileCompleted) {
            $user->setStatus(UserStatus::Active);
        }

        try {
            $this->profileImageFormHandler->handleFromForm($user, $infoForm, true);
            $this->entityManager->flush();
        } catch (UserProfileImageUploadException $exception) {
            if ($infoForm->has('profileImage')) {
                $infoForm->get('profileImage')->addError(new FormError($exception->getMessage()));
            }

            $this->addFlash('error', $exception->getMessage());

            return $this->renderProfile($user, $infoForm, $passwordForm);
        } catch (Throwable) {
            $this->addFlash('error', 'account.info_error');

            return $this->renderProfile($user, $infoForm, $passwordForm);
        }

        $locale = $this->legalLanguageResolver
            ->preferredForUser($user, $this->generalSettingsProvider->get())
            ->value;

        if ($profileCompleted) {
            $this->welcomeMailer->send($user);
            $this->addFlash('success', 'account.profile_completed');

            return $this->pendingFavoriteReturn->redirectInsteadOf(
                $user,
                $request,
                $locale,
                $this->redirectToRoute('app_public_home', [
                    '_locale' => $locale,
                ]),
            );
        }

        $this->addFlash('success', 'account.info_saved');

        return $this->redirectToRoute('app_public_profile', [
            '_locale' => $locale,
        ]);
    }

    /**
     * @param FormInterface<User> $infoForm
     * @param FormInterface<null> $passwordForm
     */
    private function handlePasswordForm(User $user, FormInterface $infoForm, FormInterface $passwordForm, string $locale): Response
    {
        if (!$passwordForm->isValid()) {
            $this->addFlash('error', 'account.review_fields');

            return $this->renderProfile($user, $infoForm, $passwordForm);
        }

        $plainPassword = $passwordForm->get('plainPassword')->getData();

        if (!is_string($plainPassword) || '' === $plainPassword) {
            $this->addFlash('error', 'account.review_fields');

            return $this->renderProfile($user, $infoForm, $passwordForm);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPasswordChosen(true);
        $user->setPasswordUpdatedAt(new DateTime());

        try {
            $this->entityManager->flush();
        } catch (Throwable) {
            $this->addFlash('error', 'account.password_error');

            return $this->renderProfile($user, $infoForm, $passwordForm);
        }

        $this->addFlash('success', 'account.password_saved');

        return $this->redirectToRoute('app_public_profile', [
            '_locale' => $locale,
        ]);
    }

    /**
     * @param FormInterface<User> $infoForm
     * @param FormInterface<null> $passwordForm
     */
    private function renderProfile(User $user, FormInterface $infoForm, FormInterface $passwordForm): Response
    {
        return $this->render('@web/account/profile.html.twig', [
            'user' => $user,
            'infoForm' => $infoForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'countryOptions' => WorldCountryCodes::viewOptions(WorldCountryCodes::DEFAULT_CHOICE_KEY),
            'timezoneOptions' => IanaTimezones::options(),
            'languageOptions' => $this->languageOptions(),
            'localeOptions' => SupportedLocale::options(),
            'timeFormatOptions' => TimeFormat::options(),
        ]);
    }
}
