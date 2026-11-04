<?php

declare(strict_types=1);

namespace App\Module\EventSubscriber;

use App\Admin\Security\EffectiveRoleChecker;
use App\Module\ModuleAvailability;
use App\Module\RequiresModule;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function str_starts_with;

/**
 * Sustituye el controlador cuando el módulo o el rol exigido no permiten el acceso.
 */
final class ModuleAccessSubscriber implements EventSubscriberInterface
{
    public const string DENIED_MESSAGE = 'Acceso denegado.';

    public function __construct(
        private readonly ModuleAvailability $moduleAvailability,
        private readonly EffectiveRoleChecker $effectiveRoleChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach ($event->getAttributes(RequiresModule::class) as $requirement) {
            if ($this->isAllowed($requirement)) {
                continue;
            }

            $request = $event->getRequest();
            $event->setController(fn (): RedirectResponse => $this->deny($request));

            return;
        }
    }

    private function isAllowed(RequiresModule $requirement): bool
    {
        if (!$this->moduleAvailability->isEnabled($requirement->slug)) {
            return false;
        }

        return null === $requirement->role || $this->effectiveRoleChecker->isGranted($requirement->role);
    }

    private function deny(Request $request): RedirectResponse
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', self::DENIED_MESSAGE);
        }

        $route = str_starts_with($request->getPathInfo(), '/admin') ? 'app_backend_home' : 'app_home';

        return new RedirectResponse($this->urlGenerator->generate($route));
    }
}
