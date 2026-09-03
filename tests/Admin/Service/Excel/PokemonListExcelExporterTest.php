<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Excel;

use App\Admin\Service\Excel\PokemonListExcelExporter;
use App\Admin\Service\Excel\PokemonSpriteImageLoader;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

use function dirname;

#[Group('unit')]
final class PokemonListExcelExporterTest extends TestCase
{
    public function testExportGeneratesValidSpreadsheetWithPokemonData(): void
    {
        $pokemon = $this->createPokemon();

        $exporter = new PokemonListExcelExporter($this->createSpriteImageLoader());
        $content = $exporter->export(
            [$pokemon],
            searchTerm: 'pika',
            sort: 'p.name',
            direction: 'asc',
            pagination: [
                'current_page' => 1,
                'total_pages' => 1,
                'total_results' => 1,
                'max_per_page' => 10,
            ],
        );

        self::assertNotSame('', $content);
        self::assertStringStartsWith('PK', $content);

        $sheet = $this->loadSheetFromContent($content);

        self::assertSame('Pokémon', $sheet->getTitle());
        self::assertSame('Listado de Pokémon', $sheet->getCell('A1')->getValue());
        self::assertSame('Total registros: 1', $sheet->getCell('A3')->getValue());
        self::assertSame('Búsqueda: pika', $sheet->getCell('A5')->getValue());
        self::assertSame('Sprite', $sheet->getCell('A8')->getValue());
        self::assertSame('Nombre', $sheet->getCell('B8')->getValue());
        self::assertSame('Altura', $sheet->getCell('D8')->getValue());
        self::assertSame('Peso', $sheet->getCell('E8')->getValue());
        self::assertSame('Estado', $sheet->getCell('J8')->getValue());
        self::assertSame('Pikachu', $sheet->getCell('B9')->getValue());
        self::assertSame('Electric', $sheet->getCell('C9')->getValue());
        self::assertSame(4, $sheet->getCell('D9')->getValue());
        self::assertSame(60, $sheet->getCell('E9')->getValue());
        self::assertSame(90, $sheet->getCell('F9')->getValue());
        self::assertSame(55, $sheet->getCell('G9')->getValue());
        self::assertSame(40, $sheet->getCell('H9')->getValue());
        self::assertSame(35, $sheet->getCell('I9')->getValue());
        self::assertSame('Visible', $sheet->getCell('J9')->getValue());
        self::assertCount(1, $sheet->getDrawingCollection());
    }

    public function testExportHandlesEmptyPokemonList(): void
    {
        $exporter = new PokemonListExcelExporter($this->createSpriteImageLoader());
        $content = $exporter->export([]);

        self::assertNotSame('', $content);

        $sheet = $this->loadSheetFromContent($content);

        self::assertSame('Sprite', $sheet->getCell('A8')->getValue());
        self::assertNull($sheet->getCell('B9')->getValue());
        self::assertCount(0, $sheet->getDrawingCollection());
    }

    private function createSpriteImageLoader(): PokemonSpriteImageLoader
    {
        return new PokemonSpriteImageLoader(
            new MockHttpClient(),
            dirname(__DIR__, 4),
        );
    }

    private function loadSheetFromContent(string $content): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pokemon_excel_test_');
        self::assertIsString($tempFile);
        file_put_contents($tempFile, $content);

        $spreadsheet = IOFactory::load($tempFile);
        unlink($tempFile);

        return $spreadsheet->getActiveSheet();
    }

    private function createPokemon(): Pokemon
    {
        $pokemonType = new PokemonType();
        $pokemonType->setName('Electric');

        $pokemon = new Pokemon();
        $pokemon->setName('Pikachu');
        $pokemon->setHeight(4);
        $pokemon->setWeight(60);
        $pokemon->setType($pokemonType);
        $pokemon->setSpeed(90);
        $pokemon->setAttack(55);
        $pokemon->setDefense(40);
        $pokemon->setHealthPoints(35);
        $pokemon->setIsHidden(false);

        return $pokemon;
    }
}
