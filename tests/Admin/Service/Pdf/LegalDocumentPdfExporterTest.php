<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Pdf;

use App\Admin\Service\Pdf\GotenbergClient;
use App\Admin\Service\Pdf\LegalDocumentPdfExporter;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

#[CoversClass(LegalDocumentPdfExporter::class)]
#[Group('unit')]
final class LegalDocumentPdfExporterTest extends TestCase
{
    public function testPdfIncludesTitleSummaryAndContentWithoutTheAcceptanceLabel(): void
    {
        $document = new LegalDocument(LegalDocumentType::PrivacyPolicy);
        $document->setName('Política de privacidad');
        $version = new LegalDocumentVersion($document, 3);
        $translation = new LegalDocumentVersionTranslation($version, 'es');
        $translation->setTitle('Título legal');
        $translation->setSummary('Resumen legal');
        $translation->setContentHtml('<p>Cuerpo <strong>legal</strong></p>');
        $translation->setAcceptanceLabel('Acepto la política');

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $view, array $context): string {
                if (str_ends_with($view, 'footer.html.twig')) {
                    return $context['document_name'] . ' v' . $context['version_number'];
                }

                self::assertSame('Título legal', $context['title']);
                self::assertSame('Resumen legal', $context['summary']);
                self::assertSame('<p>Cuerpo <strong>legal</strong></p>', $context['content_html']);
                self::assertArrayNotHasKey('acceptance_label', $context);

                return $context['title'] . $context['summary'] . $context['content_html'];
            });

        $gotenberg = $this->createMock(GotenbergClient::class);
        $gotenberg->expects(self::once())
            ->method('convertHtmlToPdf')
            ->with(
                'Título legalResumen legal<p>Cuerpo <strong>legal</strong></p>',
                'index.html',
                self::anything(),
                self::callback(static function (array $files): bool {
                    self::assertSame('Política de privacidad v3', $files['footer.html']);

                    return true;
                }),
            )
            ->willReturn('%PDF-1.4');

        $exporter = new LegalDocumentPdfExporter($twig, $gotenberg);

        self::assertSame('%PDF-1.4', $exporter->export($document, $version, $translation));
    }
}
