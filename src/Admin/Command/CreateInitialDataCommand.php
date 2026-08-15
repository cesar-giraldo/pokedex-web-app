<?php

declare(strict_types=1);

namespace App\Admin\Command;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function is_string;

use const FILTER_VALIDATE_EMAIL;

#[AsCommand(
    name: 'app:create-initial-data',
    description: 'Crea el usuario inicial de la plataforma si aún no existe.',
)]
final class CreateInitialDataCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(default::INITIAL_USER_EMAIL)%')]
        private readonly ?string $initialUserEmail,
        #[Autowire('%env(default::INITIAL_USER_PASSWORD)%')]
        private readonly ?string $initialUserPassword,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email (y nickname) del usuario inicial (alternativa a INITIAL_USER_EMAIL)',
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Contraseña del usuario inicial (alternativa a INITIAL_USER_PASSWORD)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $initialUserEmail = $this->resolveInitialUserEmail($input, $io);

        if (null === $initialUserEmail) {
            return Command::FAILURE;
        }

        if ($this->userRepository->existsByNicknameOrEmail($initialUserEmail, $initialUserEmail)) {
            $io->warning('Ya existe un usuario con el nickname o email configurado. No se creó ningún registro.');

            return Command::SUCCESS;
        }

        $plainPassword = $this->resolvePassword($input, $io);

        if (null === $plainPassword) {
            return Command::FAILURE;
        }

        $user = new User()
            ->setName('Cesar')
            ->setLastname('Giraldo')
            ->setEmail($initialUserEmail)
            ->setCountryCode(57)
            ->setCellphone('3155141481')
            ->setApplicationRoles([UserRole::Developer])
            ->setNickname($initialUserEmail)
            ->setStatus(UserStatus::Active);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Usuario inicial creado correctamente.');

        return Command::SUCCESS;
    }

    private function resolveInitialUserEmail(InputInterface $input, SymfonyStyle $io): ?string
    {
        $optionEmail = $input->getOption('email');

        if (is_string($optionEmail) && '' !== $optionEmail) {
            return mb_strtolower(trim($optionEmail));
        }

        if (is_string($this->initialUserEmail) && '' !== $this->initialUserEmail) {
            return mb_strtolower(trim($this->initialUserEmail));
        }

        $email = $io->ask(
            'Email del usuario inicial (se usará también como nickname): ',
            null,
            static function (?string $value): string {
                $value = trim((string) $value);

                if ('' === $value) {
                    throw new RuntimeException('Debes indicar un email.');
                }

                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Debes indicar un email válido.');
                }

                return mb_strtolower($value);
            },
        );

        if (!is_string($email) || '' === $email) {
            $io->error('Debes indicar un email mediante --email o INITIAL_USER_EMAIL.');

            return null;
        }

        return $email;
    }

    private function resolvePassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        $optionPassword = $input->getOption('password');

        if (is_string($optionPassword) && '' !== $optionPassword) {
            return $optionPassword;
        }

        if (is_string($this->initialUserPassword) && '' !== $this->initialUserPassword) {
            return $this->initialUserPassword;
        }

        $password = $io->askHidden('Contraseña del usuario inicial: ');

        if (!is_string($password) || '' === $password) {
            $io->error('Debes indicar una contraseña mediante --password o INITIAL_USER_PASSWORD.');

            return null;
        }

        return $password;
    }
}
