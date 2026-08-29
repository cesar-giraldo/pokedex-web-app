<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\PokemonEditType;
use App\Admin\Form\SearchPokemonType;
use App\Admin\Form\SearchUserType;
use App\Admin\Form\UserCreateType;
use App\Admin\Form\UserEditType;
use App\Admin\Form\UserProfileInfoType;
use App\Admin\Form\UserProfilePasswordType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[CoversClass(UserCreateType::class)]
final class FormMaxLengthAttributesTest extends KernelTestCase
{
    use AdminAuthenticatedClientTrait;

    public function testUserCreateTypeExposesMaxlengthAttributes(): void
    {
        self::bootKernel();

        $view = $this->createUserCreateFormView();

        $this->assertFieldMaxLength($view, 'name', 50);
        $this->assertFieldMaxLength($view, 'lastname', 70);
        $this->assertFieldMaxLength($view, 'email', 100);
        $this->assertFieldMaxLength($view, 'nickname', 20);
        $this->assertFieldMaxLength($view, 'cellphone', 12);
        $this->assertFieldMaxLength($view, 'plainPassword', 128);
        $this->assertFieldMaxLength($view, 'confirmPassword', 128);
    }

    public function testUserEditTypeExposesMaxlengthAttributes(): void
    {
        self::bootKernel();

        $view = $this->createUserEditFormView();

        $this->assertFieldMaxLength($view, 'name', 50);
        $this->assertFieldMaxLength($view, 'nickname', 20);
        $this->assertFieldMaxLength($view, 'plainPassword', 128);
    }

    public function testUserProfileInfoTypeExposesMaxlengthAttributes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $view = $formFactory->create(UserProfileInfoType::class, new User(), [
            'csrf_protection' => false,
        ])->createView();

        $this->assertFieldMaxLength($view, 'email', 100);
        $this->assertFieldMaxLength($view, 'cellphone', 12);
    }

    public function testUserProfilePasswordTypeExposesMaxlengthAttributes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $user = $this->ensureFunctionalDeveloperUser();

        $view = $formFactory->create(UserProfilePasswordType::class, null, [
            'csrf_protection' => false,
            'user' => $user,
            'password_hasher' => static::getContainer()->get(UserPasswordHasherInterface::class),
        ])->createView();

        $this->assertFieldMaxLength($view, 'currentPassword', 128);
        $this->assertFieldMaxLength($view, 'plainPassword', 128);
        $this->assertFieldMaxLength($view, 'confirmPassword', 128);
    }

    public function testSearchFormsExposeMaxlengthAttributes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        $userSearchView = $formFactory->create(SearchUserType::class)->createView();
        $this->assertFieldMaxLength($userSearchView, 'q', 30);

        $pokemonSearchView = $formFactory->create(SearchPokemonType::class)->createView();
        $this->assertFieldMaxLength($pokemonSearchView, 'q', 30);
    }

    public function testPokemonEditTypeExposesMaxlengthAttributes(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $view = $formFactory->create(PokemonEditType::class, new Pokemon())->createView();

        $this->assertFieldMaxLength($view, 'height', 3);
        $this->assertFieldMaxLength($view, 'weight', 3);
        $this->assertFieldMaxLength($view, 'attack', 3);
        $this->assertFieldMaxLength($view, 'spriteFront', 255);
        $this->assertFieldMaxLength($view, 'description', 5000);
    }

    public function testUserCreateTypeRejectsNicknameAboveMaxLength(): void
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
            'lastname' => 'Admin',
            'email' => '',
            'nickname' => str_repeat('a', 21),
            'countryCode' => '57',
            'cellphone' => '3099010003',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('nickname')->getErrors(true)->count());
    }

    private function createUserCreateFormView(): FormView
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        return $formFactory->create(UserCreateType::class, new User(), [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
        ])->createView();
    }

    private function createUserEditFormView(): FormView
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();

        $user = new User()
            ->setName('Editable')
            ->setLastname('User')
            ->setNickname('edituser1')
            ->setApplicationRoles([UserRole::Operator])
            ->setCountryCode(57)
            ->setCellphone('3099010004');

        return $formFactory->create(UserEditType::class, $user, [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
        ])->createView();
    }

    private function assertFieldMaxLength(FormView $view, string $field, int $expectedMaxLength): void
    {
        self::assertSame(
            $expectedMaxLength,
            $view[$field]->vars['attr']['maxlength'] ?? null,
            sprintf('Field "%s" maxlength mismatch.', $field),
        );
    }
}
