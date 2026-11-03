<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Data\IanaTimezones;
use App\Admin\Form\GeneralSettingsDateTimeType;
use App\Admin\Form\GeneralSettingsGeneralType;
use App\Admin\Form\GeneralSettingsLanguageType;
use App\Admin\Form\ModuleType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\SupportedLocale;
use App\Entity\Enum\TimeFormat;
use App\Entity\GeneralSettings;
use App\Entity\Module;
use App\Repository\ModuleRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function is_array;
use function is_object;

final class GeneralSettingsPageFactory
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ModuleRepository $moduleRepository,
        private readonly ModuleTreeBuilder $moduleTreeBuilder,
    ) {
    }

    /**
     * @return array{general: FormInterface<GeneralSettings>, language: FormInterface<GeneralSettings>, dateTime: FormInterface<GeneralSettings>}
     */
    public function createSettingsForms(GeneralSettings $settings): array
    {
        return [
            'general' => $this->formFactory->create(GeneralSettingsGeneralType::class, $settings),
            'language' => $this->formFactory->create(GeneralSettingsLanguageType::class, $settings),
            'dateTime' => $this->formFactory->create(GeneralSettingsDateTimeType::class, $settings),
        ];
    }

    /**
     * @return array{platformName: string, platformSlogan: string, contactSupportEmail: string}
     */
    public function persistedBranding(GeneralSettings $settings): array
    {
        return [
            'platformName' => $settings->getPlatformName() ?? '',
            'platformSlogan' => $settings->getPlatformSlogan() ?? '',
            'contactSupportEmail' => $settings->getContactSupportEmail() ?? '',
        ];
    }

    /**
     * @param FormInterface<GeneralSettings>                                                   $generalForm
     * @param FormInterface<GeneralSettings>                                                   $languageForm
     * @param FormInterface<GeneralSettings>                                                   $dateTimeForm
     * @param FormInterface<Module>|null                                                       $moduleForm
     * @param array{platformName: string, platformSlogan: string, contactSupportEmail: string} $persistedBranding
     *
     * @return array<string, mixed>
     */
    public function create(
        GeneralSettings $settings,
        FormInterface $generalForm,
        FormInterface $languageForm,
        FormInterface $dateTimeForm,
        array $persistedBranding,
        bool $editGeneral,
        bool $editLanguage,
        bool $editDateTime,
        ?FormInterface $moduleForm,
        bool $editModules,
        bool $moduleModalOpen,
        string $moduleFormMode,
    ): array {
        if (!$moduleForm instanceof FormInterface) {
            $moduleForm = $this->formFactory->create(ModuleType::class, new Module('', '', '0.00'), [
                'action' => $this->urlGenerator->generate('app_backend_module_create'),
            ]);
        }

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
            'persisted_branding' => $persistedBranding,
            'active_menu' => 'auth',
            'active_page' => 'general_settings',
            'module_form' => $moduleForm,
            'module_roots' => $this->moduleTreeBuilder->build($this->moduleRepository->findAllOrdered()),
            'module_parent_options' => $this->parentOptions($moduleForm),
            'edit_modules' => $editModules,
            'module_modal_open' => $moduleModalOpen,
            'module_form_mode' => 'edit' === $moduleFormMode ? 'edit' : 'create',
        ];
    }

    /**
     * @param FormInterface<Module> $moduleForm
     *
     * @return array<string, string>
     */
    private function parentOptions(FormInterface $moduleForm): array
    {
        $choices = $moduleForm->get('parent')->getConfig()->getOption('choices');
        if (!is_array($choices)) {
            return [];
        }

        $options = [];
        foreach ($choices as $label => $id) {
            if (is_array($id) || is_object($id)) {
                continue;
            }

            $options[(string) $id] = (string) $label;
        }

        return $options;
    }
}
