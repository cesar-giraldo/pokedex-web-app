<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Stores a SwitchUserToken in the public firewall session.
 * That session must stay on the server, with an HttpOnly cookie.
 */
final class PublicUserImpersonator
{
    public const string FIREWALL = 'public';

    public const string SESSION_KEY = '_security_public';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function start(Request $request, User $impersonator, User $target): void
    {
        $originalToken = $this->tokenStorage->getToken();

        if (!$originalToken instanceof TokenInterface) {
            throw new AccessDeniedException('No tienes permiso para impersonar usuarios.');
        }

        $switchToken = new SwitchUserToken(
            $target,
            self::FIREWALL,
            $target->getRoles(),
            $originalToken,
            $request->getUri(),
        );

        $request->getSession()->set(self::SESSION_KEY, serialize($switchToken));

        $this->logger->info('Impersonación web iniciada.', [
            'impersonator_nickname' => $impersonator->getUserIdentifier(),
            'target_nickname' => $target->getUserIdentifier(),
        ]);
    }

    public function stop(Request $request): bool
    {
        $token = $this->tokenStorage->getToken();

        if (!$token instanceof SwitchUserToken) {
            return false;
        }

        $impersonator = $token->getOriginalToken()->getUser();
        $impersonatorNickname = $impersonator instanceof User ? $impersonator->getUserIdentifier() : null;

        $this->tokenStorage->setToken(null);
        $request->getSession()->remove(self::SESSION_KEY);

        $this->logger->info('Impersonación web finalizada.', [
            'impersonator_nickname' => $impersonatorNickname,
        ]);

        return true;
    }
}
