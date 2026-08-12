<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Pdf;

use App\Admin\Service\Pdf\GotenbergClient;
use App\Admin\Service\Pdf\PdfGenerationException;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function rtrim;

final class GotenbergClientTest extends TestCase
{
    private string $gotenbergUrl;

    private int $gotenbergTimeout;

    protected function setUp(): void
    {
        $this->gotenbergUrl = $_ENV['GOTENBERG_URL'];
        $this->gotenbergTimeout = (int) $_ENV['GOTENBERG_TIMEOUT'];
    }

    public function testConvertHtmlToPdfReturnsPdfContentOnSuccess(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';
        $pdfContent = '%PDF-1.4 fake-content';

        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                rtrim($this->gotenbergUrl, '/') . '/forms/chromium/convert/html',
                $this->callback(function (array $options): bool {
                    self::assertArrayHasKey('headers', $options);
                    self::assertArrayHasKey('body', $options);
                    self::assertSame($this->gotenbergTimeout, $options['timeout']);

                    return true;
                }),
            )
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($pdfContent);

        $client = new GotenbergClient($httpClient, $logger, $this->gotenbergUrl, $this->gotenbergTimeout);

        self::assertSame($pdfContent, $client->convertHtmlToPdf($html, 'index.html', [
            'printBackground' => 'true',
        ]));
    }

    public function testConvertHtmlToPdfThrowsPdfGenerationExceptionOnHttpError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(500);

        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('Internal Server Error');

        $logger->expects($this->once())
            ->method('error');

        $client = new GotenbergClient($httpClient, $logger, $this->gotenbergUrl, $this->gotenbergTimeout);

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('Gotenberg returned HTTP 500');

        $client->convertHtmlToPdf('<html></html>');
    }

    public function testConvertHtmlToPdfThrowsPdfGenerationExceptionOnTransportFailure(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception('Connection refused'));

        $logger->expects($this->once())
            ->method('error');

        $client = new GotenbergClient($httpClient, $logger, $this->gotenbergUrl, $this->gotenbergTimeout);

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('Could not generate PDF document.');

        $client->convertHtmlToPdf('<html></html>');
    }
}
