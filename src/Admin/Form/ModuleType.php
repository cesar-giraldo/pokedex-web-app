<?php

declare(strict_types=1);

namespace App\Admin\Form;

use App\Admin\Exception\ModuleHierarchyException;
use App\Admin\Service\ModuleCatalog;
use App\Admin\Service\ModuleTreeBuilder;
use App\Admin\Service\ModuleTreeNode;
use App\Entity\Module;
use App\Repository\ModuleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function is_string;
use function str_repeat;
use function trim;

/**
 * @extends AbstractType<Module>
 */
final class ModuleType extends AbstractType
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly ModuleTreeBuilder $moduleTreeBuilder,
        private readonly ModuleCatalog $moduleCatalog,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $excludedModule = $options['excluded_module'] instanceof Module ? $options['excluded_module'] : null;

        $builder
            ->add('parent', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Sin módulo padre',
                'choices' => $this->parentChoices($excludedModule),
                'invalid_message' => 'Selecciona un módulo padre válido.',
                'getter' => static function (Module $module): ?string {
                    $parentId = $module->getParent()?->getId();

                    return null === $parentId ? null : (string) $parentId;
                },
                'setter' => function (Module $module, mixed $parentId): void {
                    if (!is_string($parentId) || '' === $parentId) {
                        $module->setParent(null);

                        return;
                    }

                    $parent = $this->moduleRepository->find((int) $parentId);
                    $module->setParent($parent instanceof Module ? $parent : null);
                },
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Indica el nombre del módulo.'),
                    new Length(
                        max: Module::NAME_MAX_LENGTH,
                        maxMessage: 'El nombre no puede superar los {{ limit }} caracteres.',
                    ),
                ],
            ])
            ->add('slug', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Indica el slug.'),
                    new Length(
                        max: Module::SLUG_MAX_LENGTH,
                        maxMessage: 'El slug no puede superar los {{ limit }} caracteres.',
                    ),
                    new Regex(
                        pattern: Module::SLUG_PATTERN,
                        message: 'El slug solo puede contener letras minúsculas y guiones bajos, y debe empezar por una letra.',
                    ),
                ],
            ])
            ->add('sortOrder', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Indica el orden.'),
                    new Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                        if (!is_string($value) || '' === trim($value)) {
                            return;
                        }

                        if (null === Module::tryNormalizeSortOrder($value)) {
                            $context->buildViolation('El orden debe ser un número mayor o igual a 0, con hasta 2 decimales.')->addViolation();
                        }
                    }),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'constraints' => [
                    new Length(
                        max: Module::DESCRIPTION_MAX_LENGTH,
                        maxMessage: 'La descripción no puede superar los {{ limit }} caracteres.',
                    ),
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $module = $event->getData();
            if (!$module instanceof Module) {
                return;
            }

            try {
                $this->moduleCatalog->assertAssignableParent($module);
            } catch (ModuleHierarchyException $exception) {
                $event->getForm()->get('parent')->addError(new FormError($exception->getMessage()));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
            'excluded_module' => null,
        ]);
        $resolver->setAllowedTypes('excluded_module', ['null', Module::class]);
    }

    /**
     * Opciones del selector de padre para el componente visual: id => etiqueta.
     *
     * @return array<string, string>
     */
    public function parentOptions(?Module $excludedModule): array
    {
        $options = [];
        foreach ($this->parentChoices($excludedModule) as $label => $id) {
            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * @return array<string, string> etiqueta => id
     */
    private function parentChoices(?Module $excludedModule): array
    {
        $excludedIds = $this->excludedIds($excludedModule);
        $choices = [];
        $seenLabels = [];

        foreach ($this->flatten($this->moduleTreeBuilder->build($this->moduleRepository->findAllOrdered())) as $node) {
            $id = $node->module->getId();
            if (null === $id || isset($excludedIds[$id])) {
                continue;
            }

            $label = str_repeat('— ', $node->depth) . $node->module->getName();
            if (isset($seenLabels[$label])) {
                $label .= ' (' . $id . ')';
            }

            $seenLabels[$label] = true;
            $choices[$label] = (string) $id;
        }

        return $choices;
    }

    /**
     * @return array<int, true>
     */
    private function excludedIds(?Module $excludedModule): array
    {
        if (!$excludedModule instanceof Module) {
            return [];
        }

        $excludedIds = [];
        foreach ($excludedModule->subtree() as $node) {
            $id = $node->getId();
            if (null !== $id) {
                $excludedIds[$id] = true;
            }
        }

        return $excludedIds;
    }

    /**
     * @param list<ModuleTreeNode> $nodes
     *
     * @return list<ModuleTreeNode>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            $flat[] = $node;
            foreach ($this->flatten($node->children) as $child) {
                $flat[] = $child;
            }
        }

        return $flat;
    }
}
