<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\Storage\UserProfileImageAccessPolicy;
use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin')]
final class UserProfileImageController extends AbstractController
{
    public function __construct(
        private readonly UserProfileImageStorage $profileImageStorage,
        private readonly UserProfileImageAccessPolicy $profileImageAccessPolicy,
    ) {
    }

    #[Route('/media/user-profile/{id}', name: 'app_backend_user_profile_image', methods: ['GET'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function show(User $user): Response
    {
        /** @var User $viewer */
        $viewer = $this->getUser();

        if (!$this->profileImageAccessPolicy->canView($viewer, $user)) {
            throw new NotFoundHttpException();
        }

        $profileImagePath = $user->getProfileImagePath();
        if (null === $profileImagePath || '' === $profileImagePath) {
            throw new NotFoundHttpException();
        }

        try {
            $stream = $this->profileImageStorage->readStream($profileImagePath);
        } catch (Throwable) {
            throw new NotFoundHttpException();
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            $output = fopen('php://output', 'w');

            if (false === $output) {
                return;
            }

            stream_copy_to_stream($stream, $output);
            fclose($stream);
            fclose($output);
        });

        $response->headers->set('Content-Type', $this->profileImageStorage->resolveMimeType($profileImagePath));
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }
}
