<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Command\SearchStorePokemonsCommand;
use App\Admin\Service\PokeAPI\PokeAPIClient;
use App\Admin\Service\PokeAPI\PokemonDetails;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Notification;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Entity\User;
use App\Notification\NotificationService;
use App\Repository\NotificationRepository;
use App\Repository\PokemonRepository;
use App\Repository\PokemonTypeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Group('unit')]
final class SearchStorePokemonsCommandTest extends TestCase
{
    public function testNotifiesDevelopersWhenAtLeastOnePokemonIsStored(): void
    {
        $notification = $this->executeImport([['name' => 'bulbasaur']], existing: false);

        self::assertInstanceOf(Notification::class, $notification);
        self::assertSame(NotificationType::PokemonsImported, $notification->getType());
        self::assertSame('Se agregó 1 Pokémon', $notification->getTitle());
        self::assertSame('El comando de importación guardó 1 Pokémon en la base de datos.', $notification->getMessage());
        self::assertSame('/admin/pokemons', $notification->getActionUrl());
    }

    public function testDoesNotNotifyWhenNoPokemonIsStored(): void
    {
        $notification = $this->executeImport([['name' => 'bulbasaur']], existing: true);

        self::assertNull($notification);
    }

    public function testDoesNotNotifyDuringDryRun(): void
    {
        $notification = $this->executeImport([['name' => 'bulbasaur']], existing: false, write: false);

        self::assertNull($notification);
    }

    /**
     * @param list<array{name: string}> $listed
     */
    private function executeImport(array $listed, bool $existing, bool $write = true): ?Notification
    {
        $found = $existing ? new Pokemon() : null;
        $pokemonRepository = new class($found) extends PokemonRepository {
            private int $counts = 0;

            public function __construct(private readonly ?Pokemon $found)
            {
            }

            public function count(array $criteria = []): int
            {
                ++$this->counts;

                return 0;
            }

            public function findOneByName(string $name): ?Pokemon
            {
                return $this->found;
            }
        };

        $type = new PokemonType();
        $type->setName('grass');
        $typeRepository = new class($type) extends PokemonTypeRepository {
            public function __construct(private readonly PokemonType $type)
            {
            }

            public function findOneByName(string $name): PokemonType
            {
                return $this->type;
            }
        };

        $api = $this->createStub(PokeAPIClient::class);
        $api->method('listPokemons')->willReturn($listed);
        if (!$existing && $write) {
            $api->method('getPokemonByName')->willReturn($this->pokemonDetails());
        }

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $persisted = null;
        $notificationManager = $this->createMock(EntityManagerInterface::class);
        if (!$existing && $write) {
            $notificationManager->expects(self::once())
                ->method('persist')
                ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                    $persisted = $entity;
                });
            $notificationManager->expects(self::once())->method('flush');
        } else {
            $notificationManager->expects(self::never())->method('persist');
        }

        $users = $this->createMock(UserRepository::class);
        if (!$existing && $write) {
            $users->expects(self::once())
                ->method('findByRoles')
                ->with([UserRole::Developer])
                ->willReturn([$this->developer()]);
        } else {
            $users->expects(self::never())->method('findByRoles');
        }

        $urls = $this->createStub(UrlGeneratorInterface::class);
        if (!$existing && $write) {
            $urls = $this->createMock(UrlGeneratorInterface::class);
            $urls->expects(self::once())
                ->method('generate')
                ->with('app_backend_pokemons')
                ->willReturn('/admin/pokemons');
        }

        $command = new SearchStorePokemonsCommand(
            $entityManager,
            $pokemonRepository,
            $typeRepository,
            $api,
            new NotificationService($notificationManager, $this->createStub(NotificationRepository::class)),
            $users,
            $urls,
        );

        $tester = new CommandTester($command);
        $status = $tester->execute([
            'limit' => '1',
            '--write' => $write ? 'true' : 'false',
        ]);

        self::assertSame(Command::SUCCESS, $status);

        return $persisted instanceof Notification ? $persisted : null;
    }

    private function pokemonDetails(): PokemonDetails
    {
        return new PokemonDetails(
            abilities: [],
            base_experience: 64,
            cries: ['latest' => '', 'legacy' => ''],
            forms: [],
            game_indices: [],
            height: 7,
            held_items: [],
            id: 1,
            is_default: true,
            location_area_encounters: '',
            moves: [],
            name: 'bulbasaur',
            order: 1,
            past_abilities: [],
            past_stats: [],
            past_types: [],
            species: ['name' => 'bulbasaur', 'url' => ''],
            sprites: ['front_default' => '', 'back_default' => ''],
            stats: [
                ['base_stat' => 45, 'effort' => 0, 'stat' => ['name' => 'hp', 'url' => '']],
                ['base_stat' => 49, 'effort' => 0, 'stat' => ['name' => 'attack', 'url' => '']],
                ['base_stat' => 49, 'effort' => 0, 'stat' => ['name' => 'defense', 'url' => '']],
                ['base_stat' => 45, 'effort' => 0, 'stat' => ['name' => 'speed', 'url' => '']],
            ],
            types: [['slot' => 1, 'type' => ['name' => 'grass', 'url' => '']]],
            weight: 69,
        );
    }

    private function developer(): User
    {
        $user = new User()->setNickname('developer');
        $property = new ReflectionProperty(User::class, 'id');
        $property->setValue($user, 7);

        return $user;
    }
}
