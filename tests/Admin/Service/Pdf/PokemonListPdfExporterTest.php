<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Pdf;

use App\Admin\Service\Pdf\GotenbergClient;
use App\Admin\Service\Pdf\PokemonListPdfExporter;
use App\Entity\Pokemon;
use App\Entity\PokemonType;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class PokemonListPdfExporterTest extends TestCase
{
    public function testExportWithoutRepeatedHeaderFooterDoesNotSendGotenbergHeaderOrFooter(): void
    {
        $pokemon = $this->createPokemon();

        $twig = $this->createMock(Environment::class);
        $gotenbergClient = $this->createMock(GotenbergClient::class);

        $twig->expects($this->once())
            ->method('render')
            ->with(
                '@admin/pokemons/export_pdf/index.html.twig',
                $this->callback(static function (array $context): bool {
                    self::assertFalse($context['repeat_header_footer']);

                    return true;
                }),
            )
            ->willReturn('<html><body>Pikachu</body></html>');

        $gotenbergClient->expects($this->once())
            ->method('convertHtmlToPdf')
            ->with(
                '<html><body>Pikachu</body></html>',
                'index.html',
                $this->callback(static function (array $options): bool {
                    self::assertSame('0.39', $options['marginTop']);
                    self::assertSame('0.39', $options['marginBottom']);

                    return true;
                }),
                [],
            )
            ->willReturn('%PDF-1.4 exported');

        $exporter = new PokemonListPdfExporter($twig, $gotenbergClient);

        self::assertSame(
            '%PDF-1.4 exported',
            $exporter->export([$pokemon], repeatHeaderFooter: false),
        );
    }

    public function testExportWithRepeatedHeaderFooterSendsGotenbergHeaderAndFooter(): void
    {
        $pokemon = $this->createPokemon();

        $twig = $this->createMock(Environment::class);
        $gotenbergClient = $this->createMock(GotenbergClient::class);

        $twig->expects($this->exactly(3))
            ->method('render')
            ->willReturnCallback(static function (string $template, array $context): string {
                if ('@admin/pokemons/export_pdf/index.html.twig' === $template) {
                    self::assertTrue($context['repeat_header_footer']);

                    return '<html><body>Pikachu</body></html>';
                }

                if ('@admin/pokemons/export_pdf/header.html.twig' === $template) {
                    return '<html><body>Header</body></html>';
                }

                self::assertSame('@admin/pokemons/export_pdf/footer.html.twig', $template);

                return '<html><body>Footer</body></html>';
            });

        $gotenbergClient->expects($this->once())
            ->method('convertHtmlToPdf')
            ->with(
                '<html><body>Pikachu</body></html>',
                'index.html',
                $this->callback(static function (array $options): bool {
                    self::assertSame('0.98', $options['marginTop']);
                    self::assertSame('0.79', $options['marginBottom']);

                    return true;
                }),
                [
                    'header.html' => '<html><body>Header</body></html>',
                    'footer.html' => '<html><body>Footer</body></html>',
                ],
            )
            ->willReturn('%PDF-1.4 exported');

        $exporter = new PokemonListPdfExporter($twig, $gotenbergClient);

        self::assertSame(
            '%PDF-1.4 exported',
            $exporter->export([$pokemon], repeatHeaderFooter: true),
        );
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
