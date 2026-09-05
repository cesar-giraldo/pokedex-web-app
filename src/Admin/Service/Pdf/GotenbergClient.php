<?php

declare(strict_types=1);

namespace App\Admin\Service\Pdf;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function rtrim;
use function sprintf;

class GotenbergClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $gotenbergUrl,
        private int $timeout = 30,
    ) {
    }

    /**
     * Converts an HTML document to PDF using Gotenberg's Chromium engine.
     *
     * @param array<string, string> $additionalFiles Extra HTML files (e.g. footer.html)
     * @param array<string, scalar> $options         Optional Gotenberg form fields
     *
     * @throws PdfGenerationException
     */
    public function convertHtmlToPdf(
        string $html,
        string $filename = 'index.html',
        array $options = [],
        array $additionalFiles = [],
    ): string {
        $url = rtrim($this->gotenbergUrl, '/') . '/forms/chromium/convert/html';

        /** @var list<DataPart> $files */
        $files = [new DataPart($html, $filename, 'text/html')];

        foreach ($additionalFiles as $additionalFilename => $additionalHtml) {
            $files[] = new DataPart($additionalHtml, $additionalFilename, 'text/html');
        }

        $formFields = [
            'files' => $files,
        ];

        foreach ($options as $key => $value) {
            $formFields[$key] = (string) $value;
        }

        $formData = new FormDataPart($formFields);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw new PdfGenerationException(
                    sprintf('Gotenberg returned HTTP %d: %s', $statusCode, $response->getContent(false))
                );
            }

            return $response->getContent();
        } catch (PdfGenerationException $exception) {
            $this->logger->error('PDF generation via Gotenberg failed', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('PDF generation via Gotenberg failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new PdfGenerationException('Could not generate PDF document.', 0, $exception);
        }
    }
}
