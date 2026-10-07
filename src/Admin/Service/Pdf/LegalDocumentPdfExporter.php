<?php

declare(strict_types=1);

namespace App\Admin\Service\Pdf;

use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use Twig\Environment;

class LegalDocumentPdfExporter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GotenbergClient $gotenbergClient,
    ) {
    }

    /**
     * @throws PdfGenerationException
     */
    public function export(
        LegalDocument $document,
        LegalDocumentVersion $version,
        LegalDocumentVersionTranslation $translation,
    ): string {
        $html = $this->twig->render('@admin/legal/export_pdf/document.html.twig', [
            'title' => $translation->getTitle(),
            'summary' => $translation->getSummary(),
            'content_html' => $translation->getContentHtml(),
        ]);

        $footerHtml = $this->twig->render('@admin/legal/export_pdf/footer.html.twig', [
            'document_name' => $document->getName(),
            'version_number' => $version->getVersionNumber(),
        ]);

        return $this->gotenbergClient->convertHtmlToPdf(
            $html,
            'index.html',
            [
                'paperWidth' => '8.27',
                'paperHeight' => '11.7',
                'marginTop' => '0.6',
                'marginBottom' => '0.79',
                'marginLeft' => '0.7',
                'marginRight' => '0.7',
                'printBackground' => 'true',
            ],
            [
                'footer.html' => $footerHtml,
            ],
        );
    }
}
