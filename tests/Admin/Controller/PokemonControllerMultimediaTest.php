<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Entity\PokemonType;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;

use function sprintf;

#[Group('functional')]
final class PokemonControllerMultimediaTest extends WebTestCase
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

    public function testMultimediaTabDisplaysUploadForm(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();

        $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="pokemon_image_upload[image]"]');
        self::assertSelectorExists('input[name="pokemon_image_upload[description]"]');
        self::assertSelectorExists('button[data-component-submit-button-label-value="Subir Imagen"]');
        self::assertSelectorTextContains('body', 'Este Pokémon aún no tiene imágenes.');
        self::assertSelectorExists('section[data-controller*="component-sortable-gallery"]');
        self::assertSelectorExists('section[data-controller*="component-image-lightbox"]');
        self::assertSelectorExists('#pokemon-image-confirm-dialog[data-controller="component-confirm-dialog"]');
    }

    public function testMultimediaUploadCreatesImageAndReloadsTab(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Subir Imagen')->form();
        $imageField = $form['pokemon_image_upload[image]'];
        self::assertInstanceOf(FileFormField::class, $imageField);
        $imageField->upload($this->createUploadedFilePath());
        $form['pokemon_image_upload[description]'] = 'Vista lateral';

        $client->submit($form);

        self::assertResponseRedirects(sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.border-success-500', 'La imagen se subió correctamente.');
        self::assertSelectorTextContains('body', 'Vista lateral');
        self::assertSelectorExists('ul.grid.grid-cols-2[data-component-sortable-gallery-target="list"]');
        self::assertSelectorExists('[data-component-sortable-gallery-target="item"]');
        self::assertSelectorExists('button.js-image-delete[aria-label="Eliminar imagen"]');
        self::assertSelectorExists('button[data-component-image-lightbox-target="item"]');
        self::assertSelectorExists('button[aria-label="Ver imagen a tamaño completo"]');
        self::assertSelectorExists('[data-component-image-lightbox-src-param*="/media/pokemon-images/"]');

        $this->entityManager?->clear();
        $updatedPokemon = $this->entityManager?->find(Pokemon::class, $pokemon->getId());
        self::assertNotNull($updatedPokemon);
        self::assertCount(1, $updatedPokemon->getImages());
        $image = $updatedPokemon->getImages()->first();
        self::assertInstanceOf(PokemonImage::class, $image);
        self::assertSame(1, $image->getSortOrder());
        self::assertSame('Vista lateral', $image->getDescription());
    }

    public function testMultimediaUploadWithoutFileShowsValidationError(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $pokemon = $this->createTestPokemon();

        $crawler = $client->request('GET', sprintf('/admin/pokemons/%d/edit/multimedia', $pokemon->getId()));
        $form = $crawler->selectButton('Subir Imagen')->form();
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Debes seleccionar una imagen.');
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

        $this->entityManager->persist($pokemon);
        $this->entityManager->flush();

        $this->pokemonId = $pokemon->getId();

        return $pokemon;
    }

    private function createUploadedFilePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pokemon-upload-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(16, 16);
        self::assertNotFalse($image);
        imagejpeg($image, $path);

        return $path;
    }
}
