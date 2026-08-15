<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;

final class PokemonControllerEditTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private ?EntityManagerInterface $entityManager = null;

    private ?int $pokemonId = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager && null !== $this->pokemonId) {
            $pokemon = $this->entityManager->find(Pokemon::class, $this->pokemonId);
            if (null !== $pokemon) {
                $type = $pokemon->getType();
                $this->entityManager->remove($pokemon);
                $this->entityManager->flush();

                if (null !== $type && str_starts_with($type->getName(), 'TestType_')) {
                    $this->entityManager->remove($type);
                    $this->entityManager->flush();
                }
            }
        }

        parent::tearDown();
    }

    public function testEditPageDisplaysPokemonData(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();

        $client->request('GET', sprintf('/admin/pokemons/%d/edit', $pokemon->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.rounded-2xl.border h3', $pokemon->getName());
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="pokemon_edit[height]"]');
    }

    public function testEditUpdatesPokemonAndRedirectsWithFlash(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $type = $pokemon->getType();
        self::assertNotNull($type);

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit', $pokemon->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar Cambios')->form([
            'pokemon_edit[height]' => '20',
            'pokemon_edit[weight]' => '300',
            'pokemon_edit[type]' => (string) $type->getId(),
            'pokemon_edit[speed]' => '90',
            'pokemon_edit[attack]' => '55',
            'pokemon_edit[defense]' => '40',
            'pokemon_edit[healthPoints]' => '35',
            'pokemon_edit[description]' => 'Descripción actualizada',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/admin/pokemons');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.border-success-500', 'se actualizó correctamente');

        $this->entityManager?->clear();
        $updatedPokemon = $this->entityManager?->find(Pokemon::class, $pokemon->getId());
        self::assertNotNull($updatedPokemon);
        self::assertSame(20, $updatedPokemon->getHeight());
        self::assertSame(300, $updatedPokemon->getWeight());
        self::assertSame('Descripción actualizada', $updatedPokemon->getDescription());
        self::assertNotNull($updatedPokemon->getLastUpdatedAt());
    }

    public function testEditWithInvalidDataShowsValidationErrors(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $type = $pokemon->getType();
        self::assertNotNull($type);

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit', $pokemon->getId()));

        $form = $crawler->selectButton('Guardar Cambios')->form([
            'pokemon_edit[height]' => '',
            'pokemon_edit[weight]' => '300',
            'pokemon_edit[type]' => (string) $type->getId(),
            'pokemon_edit[speed]' => '90',
            'pokemon_edit[attack]' => '55',
            'pokemon_edit[defense]' => '40',
            'pokemon_edit[healthPoints]' => '35',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Este campo es obligatorio');
    }

    public function testEditReturnsNotFoundForMissingPokemon(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('GET', '/admin/pokemons/999999999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    private function createTestPokemon(): Pokemon
    {
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $type = new PokemonType();
        $type->setName('TestType_' . uniqid('', true));
        $this->entityManager->persist($type);

        $pokemon = new Pokemon();
        $pokemon->setName('TestMon_' . uniqid('', true));
        $pokemon->setHeight(7);
        $pokemon->setWeight(69);
        $pokemon->setType($type);
        $pokemon->setSpeed(90);
        $pokemon->setAttack(55);
        $pokemon->setDefense(40);
        $pokemon->setHealthPoints(35);
        $pokemon->setIsHidden(false);
        $pokemon->setDescription('Descripción inicial');

        $this->entityManager->persist($pokemon);
        $this->entityManager->flush();

        $this->pokemonId = $pokemon->getId();

        return $pokemon;
    }
}
