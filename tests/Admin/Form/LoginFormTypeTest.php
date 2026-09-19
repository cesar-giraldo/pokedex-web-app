<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\LoginFormType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group('integration')]
final class LoginFormTypeTest extends KernelTestCase
{
    public function testEmptySubmissionHasValidationErrors(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(LoginFormType::class);
        $form->submit([
            '_username' => '',
            '_password' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('_username')->getErrors(true)->count());
        self::assertGreaterThan(0, $form->get('_password')->getErrors(true)->count());
    }

    public function testUsernameMustHaveMinimumLength(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(LoginFormType::class);
        $form->submit([
            '_username' => 'abc',
            '_password' => 'Secret123',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('_username')->getErrors(true)->count());
    }

    public function testUsernameRejectsValuesAboveMaxLength(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(LoginFormType::class);
        $form->submit([
            '_username' => str_repeat('a', 21),
            '_password' => 'Secret123',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('_username')->getErrors(true)->count());
    }

    public function testLoginFieldsExposeMaxlengthAttributes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $view = $formFactory->create(LoginFormType::class)->createView();

        self::assertSame(40, $view['_username']->vars['attr']['maxlength']);
        self::assertSame(128, $view['_password']->vars['attr']['maxlength']);
    }

    public function testFieldNamesMatchSecurityFirewallParameters(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(LoginFormType::class);

        self::assertSame('_username', $form->get('_username')->getName());
        self::assertSame('_password', $form->get('_password')->getName());
        self::assertSame('_remember_me', $form->get('_remember_me')->getName());
    }
}
