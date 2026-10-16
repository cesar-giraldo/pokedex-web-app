<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\Notification;
use App\Entity\User;
use App\Notification\Exception\NotificationAccessDeniedException;
use App\Notification\NotificationService;
use App\Repository\NotificationRepository;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function str_starts_with;

#[IsGranted('PUBLIC_ACCOUNT')]
final class NotificationController extends AbstractController
{
    private const int PER_PAGE = 10;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    #[Route(
        '/{_locale}/notifications',
        name: 'app_public_notifications',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        $viewer = $this->activeUser($request);

        if ($viewer instanceof Response) {
            return $viewer;
        }

        return $this->render('@web/notifications/index.html.twig', [
            'unread_count' => $this->notificationService->countUnread($viewer),
            'notifications' => $this->paginate($this->notificationRepository->queryForUser($viewer), $request),
        ]);
    }

    #[Route(
        '/{_locale}/notifications/{id}/open',
        name: 'app_public_notification_open',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT, 'id' => '\d+'],
        methods: ['POST'],
    )]
    public function open(Request $request, Notification $notification): Response
    {
        $viewer = $this->activeUser($request);

        if ($viewer instanceof Response) {
            return $viewer;
        }

        if (!$this->isCsrfTokenValid('notification_open', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        try {
            $this->notificationService->markAsRead($notification, $viewer);
        } catch (NotificationAccessDeniedException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToAction($notification->getActionUrl(), $request->getLocale());
    }

    #[Route(
        '/{_locale}/notifications/read-all',
        name: 'app_public_notifications_read_all',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['POST'],
    )]
    public function markAll(Request $request): Response
    {
        $viewer = $this->activeUser($request);

        if ($viewer instanceof Response) {
            return $viewer;
        }

        if (!$this->isCsrfTokenValid('notification_mark_all', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $this->notificationService->markAllAsRead($viewer);

        return $this->redirectToRoute('app_public_notifications', [
            '_locale' => $request->getLocale(),
        ]);
    }

    private function activeUser(Request $request): User|Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || UserStatus::Active !== $user->getStatus()) {
            return $this->redirectToRoute('app_public_profile', [
                '_locale' => $request->getLocale(),
            ]);
        }

        return $user;
    }

    /**
     * @return Pagerfanta<Notification>
     */
    private function paginate(QueryBuilder $queryBuilder, Request $request): Pagerfanta
    {
        /** @var Pagerfanta<Notification> $pager */
        $pager = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pager->setMaxPerPage(self::PER_PAGE);

        $page = $request->query->getInt('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $lastPage = $pager->getNbPages();
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $pager->setCurrentPage($page);

        return $pager;
    }

    private function redirectToAction(?string $actionUrl, string $locale): Response
    {
        if (is_string($actionUrl) && str_starts_with($actionUrl, '/') && !str_starts_with($actionUrl, '//') && !str_starts_with($actionUrl, '/\\')) {
            return $this->redirect($actionUrl);
        }

        return $this->redirectToRoute('app_public_notifications', [
            '_locale' => $locale,
        ]);
    }
}
