<?php

declare(strict_types=1);

namespace App\Tests\Api\EventSubscriber;

use App\Api\EventSubscriber\ApiExceptionSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use const JSON_THROW_ON_ERROR;

#[Group('unit')]
final class ApiExceptionSubscriberTest extends TestCase
{
    #[DataProvider('provideApiExceptionCases')]
    public function testItReturnsJsonErrorForApiRequests(
        string $path,
        Throwable $throwable,
        int $expectedStatusCode,
        string $expectedError,
    ): void {
        $subscriber = new ApiExceptionSubscriber();
        $request = Request::create($path);
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame($expectedStatusCode, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['error' => $expectedError, 'code' => $expectedStatusCode], $payload);
    }

    /**
     * @return iterable<string, array{string, Throwable, int, string}>
     */
    public static function provideApiExceptionCases(): iterable
    {
        yield 'not found' => [
            '/api/v1/pokemones/999',
            new NotFoundHttpException('Pokemon not found'),
            404,
            'Not Found',
        ];

        yield 'generic not found status text' => [
            '/api/v1/unknown',
            new NotFoundHttpException(),
            404,
            'Not Found',
        ];
    }

    public function testItDoesNotHandleNonApiRequests(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $request = Request::create('/admin/pokemons');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );

        $subscriber->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testItHidesInternalErrorDetailsInProduction(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $request = Request::create('/api/v1/pokemones');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new RuntimeException('Database connection failed'),
        );

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(500, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['error' => 'Internal Server Error', 'code' => 500], $payload);
    }
}
