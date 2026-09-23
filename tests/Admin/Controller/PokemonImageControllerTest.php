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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_reverse;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

#[Group('functional')]
final class PokemonImageControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private ?EntityManagerInterface $entityManager = null;

    /**
     * @var list<int>
     */
    private array $pokemonIds = [];

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            foreach (array_reverse($this->pokemonIds) as $pokemonId) {
                $pokemon = $this->entityManager->find(Pokemon::class, $pokemonId);
                if (null === $pokemon) {
                    continue;
                }

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

    public function testUpdateDescriptionPersistsNormalizedValue(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'Anterior');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $csrfToken = $this->getGalleryCsrfToken($client, $pokemon);

        $client->request(
            'PATCH',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['description' => '  Vista frontal  '], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false);
        self::assertSame('Vista frontal', $payload['description'] ?? null);

        $this->entityManager?->clear();
        $reloaded = $this->entityManager?->find(PokemonImage::class, $imageId);
        self::assertNotNull($reloaded);
        self::assertSame('Vista frontal', $reloaded->getDescription());
    }

    public function testUpdateDescriptionCanBeCleared(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'Quitar');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $csrfToken = $this->getGalleryCsrfToken($client, $pokemon);

        $client->request(
            'PATCH',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['description' => '   '], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('', $payload['description'] ?? 'missing');

        $this->entityManager?->clear();
        $reloaded = $this->entityManager?->find(PokemonImage::class, $imageId);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getDescription());
    }

    public function testUpdateDescriptionRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'CSRF');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $client->request(
            'PATCH',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['description' => 'Nueva'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateDescriptionRejectsImageFromAnotherPokemon(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $other = $this->createTestPokemon();
        $image = $this->createPokemonImage($other, 1, 'Ajena');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $csrfToken = $this->getGalleryCsrfToken($client, $pokemon);

        $client->request(
            'PATCH',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode(['description' => 'Nueva'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateDescriptionRejectsValueAboveMaxLength(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'Larga');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $csrfToken = $this->getGalleryCsrfToken($client, $pokemon);

        $client->request(
            'PATCH',
            sprintf('/admin/pokemons/%d/images/%d', $pokemon->getId(), $imageId),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode([
                'description' => str_repeat('a', PokemonImage::DESCRIPTION_MAX_LENGTH + 1),
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('La descripción no puede tener más de 255 caracteres.', $payload['error'] ?? null);
    }

    public function testDownloadReturnsOriginalAsAttachment(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $pokemon->setName('Bulbasaur Demo');
        $this->entityManager?->flush();

        $image = $this->createPokemonImage($pokemon, 1, 'Descargar');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $client->request(
            'GET',
            sprintf('/admin/pokemons/%d/images/%d/download', $pokemon->getId(), $imageId),
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');

        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString(
            sprintf('bulbasaur-demo-%d.jpg', $imageId),
            $disposition,
        );
        self::assertNotSame('', $client->getResponse()->getContent());
    }

    public function testDownloadRejectsImageFromAnotherPokemon(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();
        $other = $this->createTestPokemon();
        $image = $this->createPokemonImage($other, 1, 'Ajena');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $client->request(
            'GET',
            sprintf('/admin/pokemons/%d/images/%d/download', $pokemon->getId(), $imageId),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testDownloadRequiresAuthentication(): void
    {
        $client = static::createClient();
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 1, 'Privada');
        $imageId = $image->getId();
        self::assertNotNull($imageId);

        $client->request(
            'GET',
            sprintf('/admin/pokemons/%d/images/%d/download', $pokemon->getId(), $imageId),
        );

        self::assertResponseRedirects();
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
        $pokemonId = $pokemon->getId();
        self::assertNotNull($pokemonId);
        $this->pokemonIds[] = $pokemonId;

        return $pokemon;
    }

    /**
     * @return non-empty-string
     */
    private function getGalleryCsrfToken(KernelBrowser $client, Pokemon $pokemon): string
    {
        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        self::assertResponseIsSuccessful();

        $csrfToken = $crawler->filter('[data-component-sortable-gallery-csrf-token-value]')->attr('data-component-sortable-gallery-csrf-token-value');
        if (null === $csrfToken || '' === $csrfToken) {
            self::fail('No se encontró el token CSRF de la galería.');
        }

        return $csrfToken;
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
