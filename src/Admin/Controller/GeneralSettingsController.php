<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\FlashesFormValidationErrorsTrait;
use App\Admin\Data\IanaTimezones;
use App\Admin\Form\GeneralSettingsDateTimeType;
use App\Admin\Form\GeneralSettingsGeneralType;
use App\Admin\Form\GeneralSettingsLanguageType;
use App\Admin\Service\Storage\PlatformBrandingFormHandler;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Repository\GeneralSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin')]
#[IsGranted('ROLE_DEVELOPER')]
final class GeneralSettingsController extends AbstractController
{
    use FlashesFormValidationErrorsTrait;

    /**
     * Valores persistidos antes de que el formulario mapee datos no guardados.
     *
     * @var array{platformName: string, platformSlogan: string, contactSupportEmail: string}
     */
    private array $persistedBranding = [
        'platformName' => '',
        'platformSlogan' => '',
        'contactSupportEmail' => '',
    ];

    #[Route('/settings/general', name: 'app_backend_general_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        GeneralSettingsRepository $generalSettingsRepository,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        PlatformBrandingFormHandler $platformBrandingFormHandler,
    ): Response {
        $settings = $generalSettingsRepository->getOrCreateSingleton();

        if (null === $settings->getId()) {
            $entityManager->flush();
        }

        $this->persistedBranding = [
            'platformName' => $settings->getPlatformName() ?? '',
            'platformSlogan' => $settings->getPlatformSlogan() ?? '',
            'contactSupportEmail' => $settings->getContactSupportEmail() ?? '',
        ];

        $generalForm = $formFactory->create(GeneralSettingsGeneralType::class, $settings);
        $languageForm = $formFactory->create(GeneralSettingsLanguageType::class, $settings);
        $dateTimeForm = $formFactory->create(GeneralSettingsDateTimeType::class, $settings);

        $generalForm->handleRequest($request);
        $languageForm->handleRequest($request);
        $dateTimeForm->handleRequest($request);

        if ($generalForm->isSubmitted()) {
            return $this->handleGeneralFormSubmission(
                $generalForm,
                $settings,
                $entityManager,
                $formFactory,
                $platformBrandingFormHandler,
            );
        }

        if ($languageForm->isSubmitted()) {
            return $this->handleLanguageFormSubmission($languageForm, $settings, $entityManager, $formFactory);
        }

        if ($dateTimeForm->isSubmitted()) {
            return $this->handleDateTimeFormSubmission($dateTimeForm, $settings, $entityManager, $formFactory);
        }

        return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
            $settings,
            $generalForm,
            $languageForm,
            $dateTimeForm,
            false,
            false,
            false,
        ));
    }

    /**
     * @param FormInterface<mixed> $generalForm
     */
    private function handleGeneralFormSubmission(
        FormInterface $generalForm,
        GeneralSettings $settings,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        PlatformBrandingFormHandler $platformBrandingFormHandler,
    ): Response {
        $languageForm = $formFactory->create(GeneralSettingsLanguageType::class, $settings);
        $dateTimeForm = $formFactory->create(GeneralSettingsDateTimeType::class, $settings);

        if (!$generalForm->isValid()) {
            $this->flashFormValidationErrors($generalForm);

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                true,
                false,
                false,
            ));
        }

        try {
            $uploadBatch = $platformBrandingFormHandler->handleFromForm($settings, $generalForm);

            try {
                $entityManager->flush();
            } catch (Throwable $exception) {
                $uploadBatch->abort();

                throw $exception;
            }

            $uploadBatch->deleteReplaced();
        } catch (Throwable) {
            $this->addFlash('error', 'No se pudo actualizar la configuración general. Inténtelo de nuevo.');

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                true,
                false,
                false,
            ));
        }

        $this->addFlash('success', 'La configuración general se actualizó correctamente.');

        return $this->redirectToRoute('app_backend_general_settings');
    }

    /**
     * @param FormInterface<mixed> $languageForm
     */
    private function handleLanguageFormSubmission(
        FormInterface $languageForm,
        GeneralSettings $settings,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
    ): Response {
        $generalForm = $formFactory->create(GeneralSettingsGeneralType::class, $settings);
        $dateTimeForm = $formFactory->create(GeneralSettingsDateTimeType::class, $settings);

        if (!$languageForm->isValid()) {
            $this->flashFormValidationErrors($languageForm);

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                false,
                true,
                false,
            ));
        }

        try {
            $entityManager->flush();
        } catch (Throwable) {
            $this->addFlash('error', 'No se pudo actualizar la configuración de idioma. Inténtelo de nuevo.');

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                false,
                true,
                false,
            ));
        }

        $this->addFlash('success', 'La configuración de idioma se actualizó correctamente.');

        return $this->redirectToRoute('app_backend_general_settings');
    }

    /**
     * @param FormInterface<mixed> $dateTimeForm
     */
    private function handleDateTimeFormSubmission(
        FormInterface $dateTimeForm,
        GeneralSettings $settings,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
    ): Response {
        $generalForm = $formFactory->create(GeneralSettingsGeneralType::class, $settings);
        $languageForm = $formFactory->create(GeneralSettingsLanguageType::class, $settings);

        if (!$dateTimeForm->isValid()) {
            $this->flashFormValidationErrors($dateTimeForm);

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                false,
                false,
                true,
            ));
        }

        try {
            $entityManager->flush();
        } catch (Throwable) {
            $this->addFlash('error', 'No se pudo actualizar la configuración de fecha y hora. Inténtelo de nuevo.');

            return $this->render('@admin/settings/general/index.html.twig', $this->buildViewData(
                $settings,
                $generalForm,
                $languageForm,
                $dateTimeForm,
                false,
                false,
                true,
            ));
        }

        $this->addFlash('success', 'La configuración de fecha y hora se actualizó correctamente.');

        return $this->redirectToRoute('app_backend_general_settings');
    }

    /**
     * @param FormInterface<GeneralSettings> $generalForm
     * @param FormInterface<GeneralSettings> $languageForm
     * @param FormInterface<GeneralSettings> $dateTimeForm
     *
     * @return array<string, mixed>
     */
    private function buildViewData(
        GeneralSettings $settings,
        FormInterface $generalForm,
        FormInterface $languageForm,
        FormInterface $dateTimeForm,
        bool $editGeneral,
        bool $editLanguage,
        bool $editDateTime,
    ): array {
        return [
            'settings' => $settings,
            'general_form' => $generalForm,
            'language_form' => $languageForm,
            'datetime_form' => $dateTimeForm,
            'language_options' => SupportedLanguage::options(),
            'timezone_options' => IanaTimezones::options(),
            'locale_options' => SupportedLocale::options(),
            'time_format_options' => TimeFormat::options(),
            'edit_general' => $editGeneral,
            'edit_language' => $editLanguage,
            'edit_datetime' => $editDateTime,
            'persisted_branding' => $this->persistedBranding,
            'active_menu' => 'auth',
            'active_page' => 'general_settings',
        ];
    }
}
