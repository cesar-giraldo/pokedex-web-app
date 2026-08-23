<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\UserCreateType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class UserCreateTypeTest extends KernelTestCase
{
    use AdminAuthenticatedClientTrait;

    public function testApplicationRolesAcceptsAdminValue(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $form = $formFactory->create(UserCreateType::class, new User(), [
            'csrf_protection' => false,
            'show_is_hidden' => true,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
        ]);

        $view = $form->createView();
        self::assertSame('user_create[applicationRoles][]', $view['applicationRoles']->vars['full_name']);

        $form->submit([
            'name' => 'Test',
            'lastname' => 'Admin',
            'email' => '',
            'nickname' => 'test-admin-user',
            'countryCode' => '57',
            'cellphone' => '3999001001',
            'status' => 'active',
            'applicationRoles' => ['admin'],
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            foreach ($form->get('applicationRoles')->getErrors(true) as $error) {
                $errors[] = 'applicationRoles: ' . $error->getMessage();
            }

            self::fail('Form is invalid: ' . implode(' | ', $errors));
        }

        /** @var User $user */
        $user = $form->getData();
        self::assertSame([UserRole::Admin], $user->getApplicationRoles());
    }
}
