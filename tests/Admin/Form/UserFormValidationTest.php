<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\PokemonEditType;
use App\Admin\Form\SearchUserType;
use App\Admin\Form\UserCreateType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group('integration')]
final class UserFormValidationTest extends KernelTestCase
{
    use AdminAuthenticatedClientTrait;

    public function testUserCreateRejectsInvalidCellphoneFormat(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $form = $formFactory->create(UserCreateType::class, new User(), [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
        ]);

        $form->submit([
            'name' => 'Test',
            'lastname' => 'User',
            'email' => '',
            'nickname' => 'validnick',
            'countryCode' => '57',
            'cellphone' => '30012ab567',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('cellphone')->getErrors(true)->count());
    }

    public function testUserCreateRejectsInvalidNicknameFormat(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $form = $formFactory->create(UserCreateType::class, new User(), [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
        ]);

        $form->submit([
            'name' => 'Test',
            'lastname' => 'User',
            'email' => '',
            'nickname' => 'bad nick',
            'countryCode' => '57',
            'cellphone' => '3001234567',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('nickname')->getErrors(true)->count());
    }

    public function testUserCreateAcceptsWhitespaceOnlyEmailAsEmpty(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $form = $formFactory->create(UserCreateType::class, new User(), [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
        ]);

        $form->submit([
            'name' => 'Test',
            'lastname' => 'User',
            'email' => '   ',
            'nickname' => 'validnick2',
            'countryCode' => '57',
            'cellphone' => '3001234568',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertTrue($form->get('email')->isValid(), (string) $form->get('email')->getErrors(true, false));
    }

    public function testSearchUserTrimsQueryBeforeLengthValidation(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(SearchUserType::class);
        $form->submit(['q' => str_repeat('a', 30)]);

        self::assertTrue($form->isValid());

        $form = $formFactory->create(SearchUserType::class);
        $form->submit(['q' => str_repeat('a', 28) . '  ']);

        self::assertTrue($form->isValid());
    }

    public function testPokemonEditRejectsNonHttpsSpriteUrl(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(PokemonEditType::class, new Pokemon());
        $form->submit([
            'spriteFront' => 'http://example.com/sprite.png',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('spriteFront')->getErrors(true)->count());
    }
}
