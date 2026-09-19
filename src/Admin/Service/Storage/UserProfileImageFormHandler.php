<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserProfileImageFormHandler
{
    public function __construct(
        private readonly UserProfileImageStorage $profileImageStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param FormInterface<User> $form
     */
    public function handleFromForm(User $user, FormInterface $form, bool $allowRemove): void
    {
        $uploadedFile = $form->has('profileImage') ? $form->get('profileImage')->getData() : null;
        $removeProfileImage = $allowRemove
            && $form->has('removeProfileImage')
            && true === $form->get('removeProfileImage')->getData();

        $this->handle($user, $uploadedFile, $removeProfileImage, $allowRemove);
    }

    public function handle(
        User $user,
        mixed $uploadedFile,
        bool $removeProfileImage,
        bool $allowRemove,
    ): void {
        if ($uploadedFile instanceof UploadedFile) {
            $this->replaceProfileImage($user, $uploadedFile);

            return;
        }

        if ($allowRemove && $removeProfileImage) {
            $this->removeProfileImage($user);
        }
    }

    private function replaceProfileImage(User $user, UploadedFile $uploadedFile): void
    {
        $previousPath = $user->getProfileImagePath();
        $newPath = $this->profileImageStorage->allocateObjectKey($user, $uploadedFile);

        $user->setProfileImagePath($newPath);
        $this->entityManager->flush();

        try {
            $this->profileImageStorage->write($newPath, $uploadedFile);
        } catch (UserProfileImageUploadException $exception) {
            $user->setProfileImagePath($previousPath);
            $this->entityManager->flush();
            $this->profileImageStorage->tryDelete($newPath);

            throw $exception;
        }

        $this->profileImageStorage->tryDelete($previousPath);
    }

    private function removeProfileImage(User $user): void
    {
        $this->profileImageStorage->delete($user->getProfileImagePath());
        $user->setProfileImagePath(null);
        $this->entityManager->flush();
    }
}
