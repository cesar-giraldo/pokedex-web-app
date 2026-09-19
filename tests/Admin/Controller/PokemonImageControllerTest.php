<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Storage\PokemonImageStorage;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Entity\PokemonType;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

#[Group('functional')]
final class PokemonImageControllerTest extends WebTestCase
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

    public function testReorderUpdatesSortOrder(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $first = $this->createPokemonImage($pokemon, 1, 'Uno');
        $second = $this->createPokemonImage($pokemon, 2, 'Dos');

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('[data-component-sortable-gallery-csrf-token-value]')->attr('data-component-sortable-gallery-csrf-token-value');
        self::assertNotNull($csrfToken);

        $client->request(
            'POST',
            sprintf('/admin/pokemons/%d/images/reorder', $pokemon->getId()),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['ids' => [$second->getId(), $first->getId()]], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false);

        $this->entityManager?->clear();
        $reloadedFirst = $this->entityManager?->find(PokemonImage::class, $first->getId());
        $reloadedSecond = $this->entityManager?->find(PokemonImage::class, $second->getId());
        self::assertNotNull($reloadedFirst);
        self::assertNotNull($reloadedSecond);
        self::assertSame(2, $reloadedFirst->getSortOrder());
        self::assertSame(1, $reloadedSecond->getSortOrder());
    }

    public function testDeleteRemovesImage(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'Eliminar');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        $csrfToken = $crawler->filter('[data-component-sortable-gallery-csrf-token-value]')->attr('data-component-sortable-gallery-csrf-token-value');
        self::assertNotNull($csrfToken);

        $client->request(
            'DELETE',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
        );

        self::assertResponseIsSuccessful();
        $this->entityManager?->clear();
        self::assertNull($this->entityManager?->find(PokemonImage::class, $imageId));
    }

    public function testReorderRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();

        $client->request(
            'POST',
            sprintf('/admin/pokemons/%d/images/reorder', $pokemon->getId()),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['ids' => []], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
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

        $this->entityManager->persist($pokemon);
        $this->entityManager->flush();
        $this->pokemonId = $pokemon->getId();

        return $pokemon;
    }

    private function createPokemonImage(Pokemon $pokemon, int $sortOrder, string $description): PokemonImage
    {
        /** @var PokemonImageStorage $storage */
        $storage = static::getContainer()->get(PokemonImageStorage::class);
        $objectKey = $storage->upload($pokemon, $this->createUploadedFile());

        $image = new PokemonImage()
            ->setImagePath($objectKey)
            ->setDescription($description)
            ->setSortOrder($sortOrder);
        $pokemon->addImage($image);

        $this->entityManager?->persist($image);
        $this->entityManager?->flush();

        return $image;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-image-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', test: true);
    }
}
