<?php

declare(strict_types=1);

namespace App\Admin\EventSubscriber;

use App\Admin\Service\ImpersonationPolicy;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;

final class ImpersonationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ImpersonationPolicy $impersonationPolicy,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SwitchUserEvent::class => 'onSwitchUser',
        ];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $switchUser = $event->getRequest()->query->get('_switch_user')
            ?? $event->getRequest()->request->get('_switch_user');

        if ('_exit' === $switchUser) {
            $originalUser = $event->getTargetUser();

            $this->logger->info('Impersonación finalizada.', [
                'developer_nickname' => $originalUser->getUserIdentifier(),
            ]);

            return;
        }

        $target = $event->getTargetUser();

        if (!$target instanceof User) {
            throw new AccessDeniedException('El usuario objetivo no es válido.');
        }

        $impersonator = $this->resolveImpersonator($event->getToken());

        if (!$impersonator instanceof User) {
            throw new AccessDeniedException('No tienes permiso para impersonar usuarios.');
        }

        if (!$this->impersonationPolicy->canImpersonate($impersonator, $target)) {
            throw new AccessDeniedException('No puedes impersonar este usuario.');
        }

        $this->logger->info('Impersonación iniciada.', [
            'developer_nickname' => $impersonator->getUserIdentifier(),
            'target_nickname' => $target->getUserIdentifier(),
        ]);
    }

    private function resolveImpersonator(?TokenInterface $token): ?User
    {
        if ($token instanceof SwitchUserToken) {
            $originalUser = $token->getOriginalToken()->getUser();

            return $originalUser instanceof User ? $originalUser : null;
        }

        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof User ? $user : null;
    }
}
