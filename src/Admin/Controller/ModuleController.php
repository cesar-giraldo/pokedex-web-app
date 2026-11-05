<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\FlashesFormValidationErrorsTrait;
use App\Admin\Exception\ModuleHierarchyException;
use App\Admin\Form\ModuleType;
use App\Admin\Service\GeneralSettingsPageFactory;
use App\Admin\Service\ModuleCatalog;
use App\Entity\Module;
use App\Repository\GeneralSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function is_array;
use function is_bool;
use function is_string;

#[Route('/admin/settings/modules')]
#[IsGranted('ROLE_DEVELOPER')]
final class ModuleController extends AbstractController
{
    use FlashesFormValidationErrorsTrait;

    private const string CSRF_TOKEN_ID = 'module_catalog';

    public function __construct(
        private readonly ModuleCatalog $moduleCatalog,
        private readonly GeneralSettingsPageFactory $generalSettingsPageFactory,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('', name: 'app_backend_module_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $module = new Module('', '', '0.00');
        $form = $this->formFactory->create(ModuleType::class, $module, [
            'action' => $this->generateUrl('app_backend_module_create'),
        ]);

        return $this->handleForm($request, $form, $module, 'create', 'El módulo se creó correctamente.');
    }

    #[Route('/{id}', name: 'app_backend_module_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Module $module, Request $request): Response
    {
        $form = $this->formFactory->create(ModuleType::class, $module, [
            'action' => $this->generateUrl('app_backend_module_update', ['id' => $module->getId()]),
            'excluded_module' => $module,
        ]);

        return $this->handleForm($request, $form, $module, 'edit', 'El módulo se actualizó correctamente.');
    }

    #[Route('/{id}/enabled', name: 'app_backend_module_enabled', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setEnabled(Module $module, Request $request): JsonResponse
    {
        $this->assertCsrf($request);

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload) || !isset($payload['enabled']) || !is_bool($payload['enabled'])) {
            return $this->json(['error' => 'El estado del módulo no es válido.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $affected = $this->moduleCatalog->setEnabled($module, $payload['enabled']);
        } catch (ModuleHierarchyException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $modules = [];
        foreach ($affected as $node) {
            $id = $node->getId();
            if (null === $id) {
                continue;
            }

            $modules[] = [
                'id' => $id,
                'isEnabled' => $node->isEnabled(),
                'locked' => $node->hasInactiveAncestor(),
            ];
        }

        return $this->json([
            'success' => true,
            'modules' => $modules,
        ]);
    }

    /**
     * @param FormInterface<Module> $form
     */
    private function handleForm(Request $request, FormInterface $form, Module $module, string $mode, string $successMessage): Response
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashFormValidationErrors($form);

            return $this->renderPage($form, true, $mode);
        }

        try {
            $forcedDisabled = $this->moduleCatalog->save($module);
        } catch (ModuleHierarchyException $exception) {
            $form->get('parent')->addError(new FormError($exception->getMessage()));
            $this->flashFormValidationErrors($form);

            return $this->renderPage($form, true, $mode);
        } catch (Throwable) {
            $this->addFlash('error', 'No se pudo guardar el módulo. Inténtelo de nuevo.');

            return $this->renderPage($form, true, $mode);
        }

        $this->addFlash(
            'success',
            $forcedDisabled
                ? 'El módulo se guardó, pero quedó inactivo porque su módulo padre está inactivo.'
                : $successMessage,
        );

        return $this->redirectToRoute('app_backend_general_settings', ['modules' => 'edit']);
    }

    /**
     * @param FormInterface<Module> $moduleForm
     */
    private function renderPage(FormInterface $moduleForm, bool $moduleModalOpen, string $moduleFormMode): Response
    {
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        if (null === $settings->getId()) {
            $this->entityManager->flush();
        }

        $forms = $this->generalSettingsPageFactory->createSettingsForms($settings);

        return $this->render('@admin/settings/general/index.html.twig', $this->generalSettingsPageFactory->create(
            $settings,
            $forms['general'],
            $forms['language'],
            $forms['dateTime'],
            $this->generalSettingsPageFactory->persistedBranding($settings),
            false,
            false,
            false,
            $moduleForm,
            true,
            $moduleModalOpen,
            $moduleFormMode,
        ));
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN');
        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            throw new AccessDeniedHttpException('Token CSRF inválido.');
        }
    }
}
