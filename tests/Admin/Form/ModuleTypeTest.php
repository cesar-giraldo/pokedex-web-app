<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\ModuleType;
use App\Entity\Module;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

#[Group('integration')]
final class ModuleTypeTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        if (self::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();
            /** @var list<Module> $modules */
            $modules = $entityManager->createQueryBuilder()
                ->select('module')
                ->from(Module::class, 'module')
                ->where('module.slug LIKE :prefix')
                ->setParameter('prefix', 'formmod%')
                ->getQuery()
                ->getResult();

            foreach ($modules as $module) {
                $module->setParent(null);
            }

            $entityManager->flush();

            foreach ($modules as $module) {
                $entityManager->remove($module);
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testRejectsSlugOutsideTheAllowedPattern(): void
    {
        $form = $this->createForm(new Module('Nombre', 'formmod', '1'));
        $form->submit($this->payload(slug: 'No Valido'));

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('slug')->getErrors(true)->count());
    }

    public function testRejectsNegativeOrOverPreciseSortOrder(): void
    {
        $form = $this->createForm(new Module('Nombre', 'formmod', '1'));
        $form->submit($this->payload(sortOrder: '-1'));

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('sortOrder')->getErrors(true)->count());

        $another = $this->createForm(new Module('Nombre', 'formmod', '1'));
        $another->submit($this->payload(sortOrder: '1.234'));

        self::assertFalse($another->isValid());
    }

    public function testRejectsDuplicateSlug(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Module('Existente', 'formmodtaken', '1'));
        $entityManager->flush();

        $form = $this->createForm(new Module('Otro', 'formmodother', '1'));
        $form->submit($this->payload(slug: 'formmodtaken'));

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->getErrors(true)->count());
    }

    public function testRejectsDescendantAsParent(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $root = new Module('Raiz', 'formmodroot', '1');
        $child = new Module('Hijo', 'formmodchild', '1');
        $child->setParent($root);
        $entityManager->persist($root);
        $entityManager->persist($child);
        $entityManager->flush();

        $form = $this->createForm($root, $root);
        $form->submit($this->payload(
            slug: 'formmodroot',
            parent: (string) $child->getId(),
        ));

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('parent')->getErrors(true)->count());
    }

    public function testMapsValidModule(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $parent = new Module('Padre', 'formmodparent', '1');
        $entityManager->persist($parent);
        $entityManager->flush();

        $module = new Module('', '', '0');
        $form = $this->createForm($module);
        $form->submit($this->payload(
            name: ' Hijo ',
            slug: 'formmodmapped',
            sortOrder: '1.5',
            description: '   ',
            parent: (string) $parent->getId(),
        ));

        self::assertTrue($form->isValid());
        self::assertSame('Hijo', $module->getName());
        self::assertSame('formmodmapped', $module->getSlug());
        self::assertSame('1.50', $module->getSortOrder());
        self::assertNull($module->getDescription());
        self::assertSame($parent->getId(), $module->getParent()?->getId());
    }

    /**
     * @return FormInterface<Module>
     */
    private function createForm(Module $module, ?Module $excluded = null): FormInterface
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        return $formFactory->create(ModuleType::class, $module, [
            'csrf_protection' => false,
            'excluded_module' => $excluded,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function payload(
        string $name = 'Nombre',
        string $slug = 'formmodvalid',
        string $sortOrder = '1',
        string $description = '',
        string $parent = '',
    ): array {
        return [
            'parent' => $parent,
            'name' => $name,
            'slug' => $slug,
            'sortOrder' => $sortOrder,
            'description' => $description,
        ];
    }
}
