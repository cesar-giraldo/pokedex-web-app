<?php

declare(strict_types=1);

namespace App\Admin\Service\Pdf;

use App\Entity\Pokemon;
use DateTimeImmutable;
use Twig\Environment;

class PokemonListPdfExporter
{
    public function __construct(
        private Environment $twig,
        private GotenbergClient $gotenbergClient,
    ) {
    }

    /**
     * @param list<Pokemon> $pokemons
     * @param array{
     *     current_page: int,
     *     total_pages: int,
     *     total_results: int,
     *     max_per_page: int
     * } $pagination
     *
     * @throws PdfGenerationException
     */
    public function export(
        array $pokemons,
        ?string $searchTerm = null,
        string $sort = 'p.listOrder',
        string $direction = 'asc',
        array $pagination = [
            'current_page' => 1,
            'total_pages' => 1,
            'total_results' => 0,
            'max_per_page' => 0,
        ],
        bool $repeatHeaderFooter = false,
    ): string {
        $generatedAt = new DateTimeImmutable();

        $html = $this->twig->render('@admin/pokemons/export_pdf/index.html.twig', [
            'pokemons' => $pokemons,
            'search_term' => $searchTerm,
            'current_sort' => $sort,
            'current_direction' => $direction,
            'generated_at' => $generatedAt,
            'current_page' => $pagination['current_page'],
            'total_pages' => $pagination['total_pages'],
            'total_results' => $pagination['total_results'],
            'max_per_page' => $pagination['max_per_page'],
            'repeat_header_footer' => $repeatHeaderFooter,
        ]);

        $pdfOptions = [
            'paperWidth' => '8.27',
            'paperHeight' => '11.7',
            'marginTop' => '0.39',
            'marginBottom' => '0.39',
            'marginLeft' => '0.39',
            'marginRight' => '0.39',
            'printBackground' => 'true',
        ];

        $additionalFiles = [];

        if ($repeatHeaderFooter) {
            $headerHtml = $this->twig->render('@admin/pokemons/export_pdf/header.html.twig', [
                'generated_at' => $generatedAt,
            ]);

            $footerHtml = $this->twig->render('@admin/pokemons/export_pdf/footer.html.twig', [
                'generated_at' => $generatedAt,
            ]);

            $additionalFiles = [
                'header.html' => $headerHtml,
                'footer.html' => $footerHtml,
            ];

            $pdfOptions['marginTop'] = '0.98';
            $pdfOptions['marginBottom'] = '0.79';
        }

        return $this->gotenbergClient->convertHtmlToPdf(
            $html,
            'index.html',
            $pdfOptions,
            $additionalFiles,
        );
    }
}
