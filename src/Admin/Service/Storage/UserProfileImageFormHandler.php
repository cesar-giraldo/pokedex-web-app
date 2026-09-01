<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\User;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserProfileImageFormHandler
{
    public function __construct(
        private readonly UserProfileImageStorage $profileImageStorage,
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
        $newPath = $this->profileImageStorage->upload($user, $uploadedFile);

        $user->setProfileImagePath($newPath);
        $this->profileImageStorage->delete($previousPath);
    }

    private function removeProfileImage(User $user): void
    {
        $this->profileImageStorage->delete($user->getProfileImagePath());
        $user->setProfileImagePath(null);
    }
}
