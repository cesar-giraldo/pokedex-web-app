<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(SecurityHeadersSubscriber::class)]
#[Group('unit')]
final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testAddsSecurityHeadersOnMainResponse(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $event = $this->createResponseEvent(false);

        $subscriber->onKernelResponse($event);

        $headers = $event->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $headers->get('Permissions-Policy'));
        self::assertFalse($headers->has('Strict-Transport-Security'));
    }

    public function testAddsHstsInProductionOverHttps(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $event = $this->createResponseEvent(true);

        $subscriber->onKernelResponse($event);

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $event->getResponse()->headers->get('Strict-Transport-Security'),
        );
    }

    private function createResponseEvent(bool $secure): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($secure ? 'https://localhost/' : 'http://localhost/');
        $response = new Response();

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
