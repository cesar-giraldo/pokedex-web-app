<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\UserCreateType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group('integration')]
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
            'nickname' => 'tstadmin01',
            'countryCode' => '57',
            'cellphone' => '3999001001',
            'status' => 'active',
            'applicationRoles' => ['admin'],
            'timezone' => 'America/Bogota',
            'preferredLanguage' => 'es',
            'locale' => 'es-CO',
            'timeFormat' => '12h',
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

    public function testPreferredLanguageIsStoredAndOpeningTheFormDoesNotPersistIt(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $developer = $this->ensureFunctionalDeveloperUser();
        $policy = new UserManagementPolicy();
        $user = new User();

        $form = $formFactory->create(UserCreateType::class, $user, [
            'csrf_protection' => false,
            'show_is_hidden' => false,
            'assignable_roles' => $policy->getAssignableRoles($developer),
            'default_roles' => $policy->getDefaultRoles($developer),
            'enabled_languages' => ['es', 'en'],
            'website_default_language' => 'en',
        ]);

        self::assertSame('en', $form->createView()['preferredLanguage']->vars['data']);
        self::assertNull($user->getPreferredLanguage());

        $form->submit([
            'name' => 'Test',
            'lastname' => 'Admin',
            'email' => '',
            'nickname' => 'languser1',
            'countryCode' => '57',
            'cellphone' => '3999001002',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'timezone' => 'America/Bogota',
            'preferredLanguage' => 'en',
            'locale' => 'es-CO',
            'timeFormat' => '12h',
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame(SupportedLanguage::English, $user->getPreferredLanguage());
    }

    public function testPreferredLanguageRejectsALanguageThatIsNotEnabled(): void
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
            'enabled_languages' => ['es'],
            'website_default_language' => 'es',
        ]);

        $form->submit([
            'name' => 'Test',
            'lastname' => 'Admin',
            'email' => '',
            'nickname' => 'languser2',
            'countryCode' => '57',
            'cellphone' => '3999001003',
            'status' => 'active',
            'applicationRoles' => ['operator'],
            'timezone' => 'America/Bogota',
            'preferredLanguage' => 'en',
            'locale' => 'es-CO',
            'timeFormat' => '12h',
            'plainPassword' => 'Secret1',
            'confirmPassword' => 'Secret1',
        ]);

        self::assertFalse($form->get('preferredLanguage')->isValid());
    }
}
