<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\HtmlExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;
use Twig\Environment;

final class HtmlExceptionSubscriberTest extends TestCase
{
    public function testRenders404TemplateForNotFoundException(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@Twig/Exception/error404.html.twig',
                self::callback(static fn (array $context): bool => Response::HTTP_NOT_FOUND === $context['status_code']),
            )
            ->willReturn('<html>404</html>');

        $subscriber = new HtmlExceptionSubscriber($twig);
        $event = $this->createExceptionEvent(new NotFoundHttpException(), '/missing-page');

        $subscriber->onKernelException($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_NOT_FOUND, $event->getResponse()->getStatusCode());
        self::assertSame('<html>404</html>', $event->getResponse()->getContent());
    }

    public function testRenders500TemplateForGenericException(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@Twig/Exception/error500.html.twig',
                self::callback(static fn (array $context): bool => Response::HTTP_INTERNAL_SERVER_ERROR === $context['status_code']),
            )
            ->willReturn('<html>500</html>');

        $subscriber = new HtmlExceptionSubscriber($twig);
        $event = $this->createExceptionEvent(new RuntimeException('Server failure'), '/admin/pokemons');

        $subscriber->onKernelException($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $event->getResponse()->getStatusCode());
        self::assertSame('<html>500</html>', $event->getResponse()->getContent());
    }

    public function testSkipsNonHtmlErrorStatusCodes(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $subscriber = new HtmlExceptionSubscriber($twig);
        $event = $this->createExceptionEvent(new AccessDeniedHttpException(), '/admin/pokemons');

        $subscriber->onKernelException($event);

        self::assertFalse($event->hasResponse());
    }

    public function testSkipsApiRequests(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $subscriber = new HtmlExceptionSubscriber($twig);
        $event = $this->createExceptionEvent(new NotFoundHttpException(), '/api/v1/pokemon/999');

        $subscriber->onKernelException($event);

        self::assertFalse($event->hasResponse());
    }

    private function createExceptionEvent(Throwable $throwable, string $path): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
