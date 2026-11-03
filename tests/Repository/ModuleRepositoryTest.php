<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ModuleRepositoryTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        if (self::$booted) {
            $this->deleteBySlugPrefix('repomod');
        }

        parent::tearDown();
    }

    public function testFindAllOrderedSortsBySortOrderThenId(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $second = new Module('Segundo', 'repomodsecond', '2');
        $first = new Module('Primero', 'repomodfirst', '1');
        $child = new Module('Hijo', 'repomodchild', '1');
        $child->setParent($second);

        $entityManager->persist($second);
        $entityManager->persist($first);
        $entityManager->persist($child);
        $entityManager->flush();

        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);
        $slugs = [];
        foreach ($repository->findAllOrdered() as $module) {
            if (str_starts_with($module->getSlug(), 'repomod')) {
                $slugs[] = $module->getSlug();
            }
        }

        self::assertSame(['repomodfirst', 'repomodchild', 'repomodsecond'], $slugs);
        self::assertSame($second->getId(), $child->getParent()?->getId());
    }

    private function deleteBySlugPrefix(string $prefix): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var list<Module> $modules */
        $modules = $entityManager->createQueryBuilder()
            ->select('module')
            ->from(Module::class, 'module')
            ->where('module.slug LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
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
}
