<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\UserProfileImageConstraints;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserProfileImageFormValidationTest extends KernelTestCase
{
    public function testRejectsProfileImageAboveMaxSize(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $form = $formFactory->createBuilder()
            ->add('profileImage', FileType::class, [
                'mapped' => false,
                'constraints' => UserProfileImageConstraints::upload(),
            ])
            ->getForm();

        $path = tempnam(sys_get_temp_dir(), 'oversized-profile-image-');
        self::assertNotFalse($path);
        file_put_contents($path, str_repeat('a', 7 * 1024 * 1024));

        $form->submit([
            'profileImage' => new UploadedFile($path, 'avatar.jpg', 'image/jpeg', test: true),
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('profileImage')->getErrors(true)->count());
    }

    public function testRejectsInvalidProfileImageMimeType(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $form = $formFactory->createBuilder()
            ->add('profileImage', FileType::class, [
                'mapped' => false,
                'constraints' => UserProfileImageConstraints::upload(),
            ])
            ->getForm();

        $path = tempnam(sys_get_temp_dir(), 'invalid-profile-image-');
        self::assertNotFalse($path);
        file_put_contents($path, 'not-an-image');

        $form->submit([
            'profileImage' => new UploadedFile($path, 'avatar.txt', 'text/plain', test: true),
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('profileImage')->getErrors(true)->count());
    }
}
