<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use Twig\Environment;

/**
 * Renders custom HTML error pages for non-API requests in every environment,
 * including dev (where Symfony would otherwise show the debug exception page).
 */
final class HtmlExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isApiRequest($request)) {
            return;
        }

        $throwable = $event->getThrowable();
        $statusCode = $this->resolveStatusCode($throwable);
        $template = $this->resolveTemplate($throwable, $statusCode);

        if (null === $template) {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render($template, [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
            ]),
            $statusCode,
        ));
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    private function resolveStatusCode(Throwable $throwable): int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function resolveTemplate(Throwable $throwable, int $statusCode): ?string
    {
        if ($throwable instanceof NotFoundHttpException || Response::HTTP_NOT_FOUND === $statusCode) {
            return '@Twig/Exception/error404.html.twig';
        }

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $statusCode) {
            return '@Twig/Exception/error500.html.twig';
        }

        return null;
    }
}
