<?php

declare(strict_types=1);

namespace App\Admin\Form\Concerns;

use App\Admin\Form\UserProfileImageConstraints;
use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

trait UserProfileImageFormFields
{
    /**
     * @param FormBuilderInterface<User|null> $builder
     */
    private function addProfileImageFields(FormBuilderInterface $builder, bool $allowRemove): void
    {
        $builder->add('profileImage', FileType::class, [
            'label' => false,
            'mapped' => false,
            'required' => false,
            'constraints' => UserProfileImageConstraints::upload(),
        ]);

        if ($allowRemove && $this->userHasStoredProfileImage($builder->getData())) {
            $builder->add('removeProfileImage', CheckboxType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
            ]);
        }
    }

    private function userHasStoredProfileImage(mixed $data): bool
    {
        if (!$data instanceof User) {
            return false;
        }

        $path = $data->getProfileImagePath();

        return null !== $path && '' !== trim($path);
    }
}
