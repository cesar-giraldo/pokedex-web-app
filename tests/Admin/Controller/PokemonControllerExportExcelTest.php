<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Excel\ExcelGenerationException;
use App\Admin\Service\Excel\PokemonListExcelExporter;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class PokemonControllerExportExcelTest extends WebTestCase
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

    public function testExportExcelReturnsSpreadsheetResponse(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->createTestPokemon();

        $excelExporter = $this->createMock(PokemonListExcelExporter::class);
        $excelExporter->expects($this->once())
            ->method('export')
            ->willReturn('PK mock-xlsx-content');

        static::getContainer()->set(PokemonListExcelExporter::class, $excelExporter);

        $client->request('GET', '/admin/pokemons/export/excel');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringStartsWith('PK', $content);
    }

    public function testExportExcelRedirectsWithFlashWhenGenerationFails(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->createTestPokemon();

        $excelExporter = $this->createMock(PokemonListExcelExporter::class);
        $excelExporter->expects($this->once())
            ->method('export')
            ->willThrowException(new ExcelGenerationException('Write failed'));

        static::getContainer()->set(PokemonListExcelExporter::class, $excelExporter);

        $client->request('GET', '/admin/pokemons/export/excel');

        self::assertResponseRedirects('/admin/pokemons');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No se pudo generar el archivo Excel');
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
