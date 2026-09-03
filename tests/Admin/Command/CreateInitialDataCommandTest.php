<?php

declare(strict_types=1);

namespace App\Tests\Admin\Command;

use App\Entity\Enum\UserRole;
use App\Entity\GeneralSettings;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class CreateInitialDataCommandTest extends KernelTestCase
{
    private const string INITIAL_EMAIL = 'initial-user@example.com';

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
}
