<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Entity\PokemonType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function fclose;
use function sprintf;
use function strlen;

#[Group('functional')]
final class PokemonImageControllerTest extends WebTestCase
{
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

    public function testPublicMediaIsAvailableWithoutAuthentication(): void
    {
        $client = static::createClient();
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon);

        $client->request('GET', sprintf('/media/pokemon-images/%s', $image->getPublicToken()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testThumbVariantIsWebpAndSmallerThanOriginal(): void
    {
        $client = static::createClient();
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon, 800);

        $client->request('GET', sprintf('/media/pokemon-images/%s/thumb', $image->getPublicToken()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');

        /** @var PokemonImageStorage $storage */
        $storage = static::getContainer()->get(PokemonImageStorage::class);
        $originalStream = $storage->readStream($image->getImagePath());
        $thumbStream = $storage->readStream($image->getImagePath(), ImageVariant::Thumb);

        try {
            $originalSize = strlen((string) stream_get_contents($originalStream));
            $thumbSize = strlen((string) stream_get_contents($thumbStream));
        } finally {
            fclose($originalStream);
            fclose($thumbStream);
        }

        self::assertGreaterThan(0, $originalSize);
        self::assertGreaterThan(0, $thumbSize);
        self::assertLessThan($originalSize, $thumbSize);
    }

    public function testInvalidVariantIsNotRouted(): void
    {
        $client = static::createClient();
        $pokemon = $this->createTestPokemon();
        $image = $this->createPokemonImage($pokemon);

        $client->request('GET', sprintf('/media/pokemon-images/%s/avatar', $image->getPublicToken()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testMissingImageReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/media/pokemon-images/1111111111111111111111');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNumericIdentifierIsNotRouted(): void
    {
        $client = static::createClient();
        $client->request('GET', '/media/pokemon-images/1');

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

        $this->entityManager->persist($pokemon);
        $this->entityManager->flush();
        $this->pokemonId = $pokemon->getId();

        return $pokemon;
    }

    /**
     * @param int<1, max> $size
     */
    private function createPokemonImage(Pokemon $pokemon, int $size = 16): PokemonImage
    {
        /** @var PokemonImageStorage $storage */
        $storage = static::getContainer()->get(PokemonImageStorage::class);
        $objectKey = $storage->upload($pokemon, $this->createUploadedFile($size));
        $image = new PokemonImage()
            ->setImagePath($objectKey)
            ->setSortOrder(1);
        $pokemon->addImage($image);

        $this->entityManager?->persist($image);
        $this->entityManager?->flush();

        return $image;
    }

    /**
     * @param int<1, max> $size
     */
    private function createUploadedFile(int $size = 16): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-public-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor($size, $size);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', test: true);
    }
}
