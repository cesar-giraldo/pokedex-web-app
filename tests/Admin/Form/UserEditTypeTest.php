<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\UserEditType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class UserEditTypeTest extends KernelTestCase
{
    use AdminAuthenticatedClientTrait;

    public function testPasswordFieldsAreOptionalOnEdit(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $user = new User()
            ->setName('Editable')
            ->setLastname('User')
            ->setNickname('editable-user')
            ->setApplicationRoles([UserRole::Operator])
            ->setCountryCode(57)
            ->setCellphone('3999002001');
        $user->setPassword('existing-hash');

        $form = $formFactory->create(UserEditType::class, $user, [
            'csrf_protection' => false,
            'show_is_hidden' => true,
            'assignable_roles' => $policy->getAssignableRoles($developer),
        ]);

        $view = $form->createView();
        self::assertFalse($view['plainPassword']->vars['required']);
        self::assertFalse($view['confirmPassword']->vars['required']);

        $form->submit([
            'name' => 'Editable',
            'lastname' => 'User',
            'email' => '',
            'nickname' => 'editable-user',
            'countryCode' => '57',
            'cellphone' => '3999002001',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => '',
            'confirmPassword' => '',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('existing-hash', $user->getPassword());
    }

    public function testEditAllowsAddingEmailToUserWithoutPreviousEmail(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $user = new User()
            ->setName('NoEmail')
            ->setLastname('User')
            ->setEmail(null)
            ->setNickname('no-email-user')
            ->setApplicationRoles([UserRole::Operator])
            ->setCountryCode(57)
            ->setCellphone('3999002002');
        $user->setPassword('existing-hash');

        $form = $formFactory->create(UserEditType::class, $user, [
            'csrf_protection' => false,
            'show_is_hidden' => true,
            'assignable_roles' => $policy->getAssignableRoles($developer),
        ]);

        $form->submit([
            'name' => 'NoEmail',
            'lastname' => 'User',
            'email' => 'new-email@example.com',
            'nickname' => 'no-email-user',
            'countryCode' => '57',
            'cellphone' => '3999002002',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => '',
            'confirmPassword' => '',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('new-email@example.com', $user->getEmail());
    }
}
