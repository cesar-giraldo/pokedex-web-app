<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Pdf\PdfGenerationException;
use App\Admin\Service\Pdf\PokemonListPdfExporter;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PokemonControllerExportPdfTest extends WebTestCase
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

    public function testExportPdfReturnsPdfResponse(): void
    {
        $client = static::createClient();
        $this->createTestPokemon();

        $pdfExporter = $this->createMock(PokemonListPdfExporter::class);
        $pdfExporter->expects($this->once())
            ->method('export')
            ->willReturn('%PDF-1.4 test-content');

        static::getContainer()->set(PokemonListPdfExporter::class, $pdfExporter);

        $client->request('GET', '/admin/pokemons/export/pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringStartsWith('%PDF', $content);
    }

    public function testExportPdfRedirectsWithFlashWhenGenerationFails(): void
    {
        $client = static::createClient();
        $this->createTestPokemon();

        $pdfExporter = $this->createMock(PokemonListPdfExporter::class);
        $pdfExporter->expects($this->once())
            ->method('export')
            ->willThrowException(new PdfGenerationException('Gotenberg unavailable'));

        static::getContainer()->set(PokemonListPdfExporter::class, $pdfExporter);

        $client->request('GET', '/admin/pokemons/export/pdf');

        self::assertResponseRedirects('/admin/pokemons');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No se pudo generar el PDF');
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
