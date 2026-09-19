<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Admin\Service\Storage\PokemonImageStorage;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Entity\PokemonType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function sprintf;

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

        $client->request('GET', sprintf('/media/pokemon-images/%d', $image->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testMissingImageReturnsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/media/pokemon-images/999999999');

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

    private function createPokemonImage(Pokemon $pokemon): PokemonImage
    {
        /** @var PokemonImageStorage $storage */
        $storage = static::getContainer()->get(PokemonImageStorage::class);
        $objectKey = $storage->upload($pokemon, $this->createUploadedFile());
        $image = new PokemonImage()
            ->setImagePath($objectKey)
            ->setSortOrder(1);
        $pokemon->addImage($image);

        $this->entityManager?->persist($image);
        $this->entityManager?->flush();

        return $image;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-public-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', test: true);
    }
}
