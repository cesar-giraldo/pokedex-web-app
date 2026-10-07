<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\AdminPaginatorTrait;
use App\Entity\Notification;
use App\Entity\User;
use App\Legal\LegalNotificationDestination;
use App\Notification\Exception\NotificationAccessDeniedException;
use App\Notification\NotificationService;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function str_starts_with;

#[Route('/admin')]
#[IsGranted('ROLE_OPERATOR')]
final class NotificationController extends AbstractController
{
    use AdminPaginatorTrait;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationRepository $notificationRepository,
        private readonly LegalNotificationDestination $legalNotificationDestination,
    ) {
    }

    #[Route('/notifications', name: 'app_backend_notifications', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $viewer = $this->currentUser();

        return $this->render('@admin/notifications/index.html.twig', [
            'active_menu' => 'notifications',
            'active_page' => 'notifications',
            'unread_count' => $this->notificationService->countUnread($viewer),
            ...$this->getPagination($this->notificationRepository->queryForUser($viewer), $request),
        ]);
    }

    #[Route('/notifications/{id}/open', name: 'app_backend_notification_open', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function open(Request $request, Notification $notification): Response
    {
        if (!$this->isCsrfTokenValid('notification_open', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $viewer = $this->currentUser();

        try {
            $this->notificationService->markAsRead($notification, $viewer);
        } catch (NotificationAccessDeniedException) {
            throw $this->createNotFoundException();
        }

        $publicUrl = $this->legalNotificationDestination->urlForAcceptedVersion($viewer, $notification);

        if (null !== $publicUrl) {
            return $this->redirect($publicUrl);
        }

        return $this->redirectToAction($notification->getActionUrl());
    }

    #[Route('/notifications/read-all', name: 'app_backend_notifications_read_all', methods: ['POST'])]
    public function markAll(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('notification_mark_all', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $this->notificationService->markAllAsRead($this->currentUser());

        return $this->redirectToRoute('app_backend_notifications');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function redirectToAction(?string $actionUrl): Response
    {
        if (is_string($actionUrl) && str_starts_with($actionUrl, '/') && !str_starts_with($actionUrl, '//') && !str_starts_with($actionUrl, '/\\')) {
            return $this->redirect($actionUrl);
        }

        return $this->redirectToRoute('app_backend_notifications');
    }
}
