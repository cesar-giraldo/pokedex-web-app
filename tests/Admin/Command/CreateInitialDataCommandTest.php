<?php

declare(strict_types=1);

namespace App\Tests\Admin\Command;

use App\Admin\Service\InitialModules;
use App\Entity\Enum\UserRole;
use App\Entity\GeneralSettings;
use App\Entity\Module;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use App\Repository\ModuleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

#[Group('integration')]
final class CreateInitialDataCommandTest extends KernelTestCase
{
    private const string INITIAL_EMAIL = 'initial-user@example.com';

    /** @var list<int> */
    private array $moduleIdsToDelete = [];

    protected function tearDown(): void
    {
        if (self::$booted && [] !== $this->moduleIdsToDelete) {
            $this->deleteModulesById($this->moduleIdsToDelete);
        }

        parent::tearDown();
    }

    public function testCreatesInitialUserWhenItDoesNotExist(): void
    {
        self::bootKernel();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $existingByPhone = $userRepository->createQueryBuilder('u')
            ->andWhere('u.countryCode = :countryCode')
            ->andWhere('u.cellphone = :cellphone')
            ->setParameter('countryCode', 57)
            ->setParameter('cellphone', '3155141481')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingByPhone instanceof User && self::INITIAL_EMAIL !== $existingByPhone->getEmail()) {
            self::markTestSkipped('El teléfono del usuario inicial ya está asignado a otra cuenta.');
        }

        if ($userRepository->findOneByNickname(self::INITIAL_EMAIL) instanceof User) {
            $user = $userRepository->findOneByNickname(self::INITIAL_EMAIL);
            self::assertSame(self::INITIAL_EMAIL, $user->getEmail());
            self::assertSame(self::INITIAL_EMAIL, $user->getNickname());

            return;
        }

        $command = static::getContainer()->get('App\Admin\Command\CreateInitialDataCommand');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--email' => self::INITIAL_EMAIL,
            '--password' => 'InitialSecret123',
        ]);

        if (0 !== $exitCode) {
            self::markTestSkipped('No se pudo crear el usuario inicial en la base de datos de pruebas.');
        }

        self::assertSame(0, $exitCode);

        $user = $userRepository->findOneByNickname(self::INITIAL_EMAIL);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(self::INITIAL_EMAIL, $user->getEmail());
        self::assertSame(self::INITIAL_EMAIL, $user->getNickname());
        self::assertSame([UserRole::Developer], $user->getApplicationRoles());
        self::assertSame(GeneralSettings::DEFAULT_TIMEZONE, $user->getTimezone());
        self::assertSame(GeneralSettings::DEFAULT_LOCALE, $user->getLocale());
        self::assertSame(GeneralSettings::DEFAULT_TIME_FORMAT, $user->getTimeFormat());
    }

    public function testCreatesGeneralSettingsWhenTheyDoNotExist(): void
    {
        self::bootKernel();

        /** @var GeneralSettingsRepository $generalSettingsRepository */
        $generalSettingsRepository = static::getContainer()->get(GeneralSettingsRepository::class);

        if ($generalSettingsRepository->findSingleton() instanceof GeneralSettings) {
            self::markTestSkipped('Ya existen configuraciones generales en la base de datos de pruebas.');
        }

        $command = static::getContainer()->get('App\Admin\Command\CreateInitialDataCommand');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--email' => 'settings-seed@example.com',
            '--password' => 'InitialSecret123',
        ]);

        if (0 !== $exitCode) {
            self::markTestSkipped('No se pudo ejecutar el comando de datos iniciales en la base de datos de pruebas.');
        }

        $settings = $generalSettingsRepository->findSingleton();

        self::assertInstanceOf(GeneralSettings::class, $settings);
        self::assertTrue($settings->isShowHiddenUsers());
        self::assertSame(['es'], $settings->getEnabledLanguages());
        self::assertSame('es', $settings->getWebsiteDefaultLanguage());
        self::assertSame(GeneralSettings::DEFAULT_TIMEZONE, $settings->getDefaultTimezone());
        self::assertSame(GeneralSettings::DEFAULT_LOCALE, $settings->getDefaultLocale());
        self::assertSame(GeneralSettings::DEFAULT_TIME_FORMAT, $settings->getDefaultTimeFormat());
    }

    public function testDoesNotDuplicateGeneralSettings(): void
    {
        self::bootKernel();

        /** @var GeneralSettingsRepository $generalSettingsRepository */
        $generalSettingsRepository = static::getContainer()->get(GeneralSettingsRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        if (!$generalSettingsRepository->findSingleton() instanceof GeneralSettings) {
            self::markTestSkipped('No existen configuraciones generales en la base de datos de pruebas.');
        }

        if (!$userRepository->existsByNicknameOrEmail(self::INITIAL_EMAIL, self::INITIAL_EMAIL)) {
            self::markTestSkipped('No existe el usuario inicial esperado para validar la duplicación de settings.');
        }

        $command = static::getContainer()->get('App\Admin\Command\CreateInitialDataCommand');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--email' => self::INITIAL_EMAIL,
            '--password' => 'InitialSecret123',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Ya existen configuraciones generales', $tester->getDisplay());
    }

    public function testDoesNotDuplicateInitialUser(): void
    {
        self::bootKernel();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $existingByPhone = $userRepository->createQueryBuilder('u')
            ->andWhere('u.countryCode = :countryCode')
            ->andWhere('u.cellphone = :cellphone')
            ->setParameter('countryCode', 57)
            ->setParameter('cellphone', '3155141481')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingByPhone instanceof User && self::INITIAL_EMAIL !== $existingByPhone->getEmail()) {
            self::markTestSkipped('El teléfono del usuario inicial ya está asignado a otra cuenta.');
        }

        $command = static::getContainer()->get('App\Admin\Command\CreateInitialDataCommand');
        $tester = new CommandTester($command);

        if (!$userRepository->existsByNickname(self::INITIAL_EMAIL)) {
            $tester->execute([
                '--email' => self::INITIAL_EMAIL,
                '--password' => 'InitialSecret123',
            ]);
        }

        $exitCode = $tester->execute([
            '--email' => self::INITIAL_EMAIL,
            '--password' => 'InitialSecret123',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Ya existe un usuario', $tester->getDisplay());
    }

    public function testCreatesMissingInitialModulesWithoutDuplicatingThem(): void
    {
        self::bootKernel();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $existingByPhone = $userRepository->createQueryBuilder('u')
            ->andWhere('u.countryCode = :countryCode')
            ->andWhere('u.cellphone = :cellphone')
            ->setParameter('countryCode', 57)
            ->setParameter('cellphone', '3155141481')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingByPhone instanceof User && self::INITIAL_EMAIL !== $existingByPhone->getEmail()) {
            self::markTestSkipped('El teléfono del usuario inicial ya está asignado a otra cuenta.');
        }

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = static::getContainer()->get(ModuleRepository::class);
        $definitions = new InitialModules()->definitions();
        $existingIds = [];

        foreach ($definitions as $definition) {
            $module = $moduleRepository->findOneBy(['slug' => $definition->slug]);
            if ($module instanceof Module && null !== $module->getId()) {
                $existingIds[$definition->slug] = $module->getId();
            }
        }

        $command = static::getContainer()->get('App\Admin\Command\CreateInitialDataCommand');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--email' => self::INITIAL_EMAIL,
            '--password' => 'InitialSecret123',
        ]);

        self::assertSame(0, $exitCode);

        $display = $tester->getDisplay();
        $idsAfterCreate = [];

        foreach ($definitions as $definition) {
            $module = $moduleRepository->findOneBy(['slug' => $definition->slug]);

            self::assertInstanceOf(Module::class, $module);
            self::assertSame($definition->name, $module->getName());
            self::assertSame($definition->sortOrder, $module->getSortOrder());
            self::assertSame($definition->description, $module->getDescription());
            self::assertSame($definition->isEnabled, $module->isEnabled());
            self::assertSame($definition->parentSlug, $module->getParent()?->getSlug());

            $id = $module->getId();
            self::assertNotNull($id);
            $idsAfterCreate[$definition->slug] = $id;

            if (isset($existingIds[$definition->slug])) {
                self::assertSame($existingIds[$definition->slug], $id);

                continue;
            }

            $this->moduleIdsToDelete[] = $id;
            self::assertStringContainsString($definition->slug, $display);

            if (null === $definition->parentSlug) {
                self::assertStringContainsString('Módulos creados', $display);
                self::assertStringContainsString(sprintf('%s (%s)', $definition->name, $definition->slug), $display);

                continue;
            }

            self::assertStringContainsString('Submódulos creados', $display);
            self::assertStringContainsString(
                sprintf('%s (%s) → %s', $definition->name, $definition->slug, $definition->parentSlug),
                $display,
            );
        }

        if ([] === $this->moduleIdsToDelete) {
            self::assertStringContainsString('Ya existen los módulos iniciales', $display);
        }

        $exitCode = $tester->execute([
            '--email' => self::INITIAL_EMAIL,
            '--password' => 'InitialSecret123',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Ya existen los módulos iniciales', $tester->getDisplay());

        foreach ($definitions as $definition) {
            $module = $moduleRepository->findOneBy(['slug' => $definition->slug]);

            self::assertSame($idsAfterCreate[$definition->slug], $module?->getId());
        }
    }

    /**
     * @param list<int> $ids
     */
    private function deleteModulesById(array $ids): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var list<Module> $modules */
        $modules = $entityManager->createQueryBuilder()
            ->select('module')
            ->from(Module::class, 'module')
            ->where('module.id IN (:ids)')
            ->setParameter('ids', $ids)
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
