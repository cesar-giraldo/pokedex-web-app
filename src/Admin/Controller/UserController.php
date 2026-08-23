<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\AdminPaginatorTrait;
use App\Admin\Controller\Concerns\FlashesFormValidationErrorsTrait;
use App\Admin\Data\AmericasCountryCodes;
use App\Admin\Form\SearchUserType;
use App\Admin\Form\UserCreateType;
use App\Admin\Form\UserEditType;
use App\Admin\Form\UserProfileInfoType;
use App\Admin\Form\UserProfilePasswordType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function is_string;
use function sprintf;

#[Route('/admin')]
final class UserController extends AbstractController
{
    use AdminPaginatorTrait;
    use FlashesFormValidationErrorsTrait;

    #[Route('/users', name: 'app_backend_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        UserRepository $userRepository,
        UserManagementPolicy $userManagementPolicy,
        Request $request,
    ): Response {
        $form = $this->createForm(SearchUserType::class);
        $form->handleRequest($request);

        $term = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $term = $data['q'] ?? null;
        }

        $sort = $request->query->get('sort', 'u.createdAt');
        $direction = $request->query->get('direction', 'desc');
        $isDeveloper = $this->isGranted('ROLE_DEVELOPER');

        $queryBuilder = $userRepository->findBackendUsersQueryBuilder(
            $term,
            $sort,
            $direction,
            [
                'excludeDevelopers' => !$isDeveloper,
                'excludeHidden' => !$isDeveloper,
            ],
        );

        $pagination = $this->getPagination($queryBuilder, $request);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $editableUserIds = [];
        foreach ($pagination['entities']->getCurrentPageResults() as $listedUser) {
            if ($listedUser instanceof User && $userManagementPolicy->canEdit($currentUser, $listedUser)) {
                $editableUserIds[] = $listedUser->getId();
            }
        }

        return $this->render('@admin/users/index.html.twig', [
            'controller_name' => 'UserController',
            'active_menu' => 'user_profile',
            'active_page' => 'user_list',
            'search_form' => $form->createView(),
            'current_sort' => $sort,
            'current_direction' => $direction,
            'search_term' => $term,
            'current_user' => $currentUser,
            'editable_user_ids' => $editableUserIds,
            ...$pagination,
        ]);
    }

    #[Route('/users/new', name: 'app_backend_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserManagementPolicy $userManagementPolicy,
    ): Response {
        /** @var User $editor */
        $editor = $this->getUser();

        $user = new User();
        $formOptions = $this->buildFormOptions($editor, $userManagementPolicy, true);

        $form = $this->createForm(UserCreateType::class, $user, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('error', 'Revisa los campos marcados.');

                return $this->render('@admin/users/new.html.twig', $this->buildFormViewData($user, $form, $formOptions));
            }
            /** @var list<UserRole> $selectedRoles */
            $selectedRoles = $user->getApplicationRoles();

            if (!$userManagementPolicy->canAssignRoles($editor, $selectedRoles)) {
                throw new AccessDeniedHttpException('No tienes permiso para asignar uno o más roles seleccionados.');
            }

            $plainPassword = $form->get('plainPassword')->getData();
            if (!is_string($plainPassword) || '' === $plainPassword) {
                $this->addFlash('error', 'Revisa los campos marcados.');

                return $this->render('@admin/users/new.html.twig', $this->buildFormViewData($user, $form, $formOptions));
            }

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            try {
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (Throwable) {
                $this->addFlash('error', 'No se pudo crear el usuario. Inténtelo de nuevo.');

                return $this->render('@admin/users/new.html.twig', $this->buildFormViewData($user, $form, $formOptions));
            }

            $this->addFlash('success', sprintf('El usuario "%s" se creó correctamente.', $user->getNickname()));

            return $this->redirectToRoute('app_backend_users');
        }

        return $this->render('@admin/users/new.html.twig', $this->buildFormViewData($user, $form, $formOptions));
    }

    #[Route('/users/{id}/edit', name: 'app_backend_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserManagementPolicy $userManagementPolicy,
    ): Response {
        /** @var User $editor */
        $editor = $this->getUser();

        if ($editor->getId() === $user->getId()) {
            return $this->redirectToRoute('app_backend_user_profile');
        }

        if (!$userManagementPolicy->canEdit($editor, $user)) {
            throw new AccessDeniedHttpException('No tienes permiso para editar este usuario.');
        }

        $formOptions = $this->buildFormOptions($editor, $userManagementPolicy, false);

        $form = $this->createForm(UserEditType::class, $user, $formOptions);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->addFlash('error', 'Revisa los campos marcados.');
            } else {
                /** @var list<UserRole> $selectedRoles */
                $selectedRoles = $user->getApplicationRoles();

                if (!$userManagementPolicy->canAssignRoles($editor, $selectedRoles)) {
                    throw new AccessDeniedHttpException('No tienes permiso para asignar uno o más roles seleccionados.');
                }

                $plainPassword = $form->get('plainPassword')->getData();
                if (is_string($plainPassword) && '' !== $plainPassword) {
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                    $user->setPasswordUpdatedAt(new DateTime());
                }

                try {
                    $entityManager->flush();
                } catch (Throwable) {
                    $this->addFlash('error', 'No se pudo actualizar el usuario. Inténtelo de nuevo.');

                    return $this->render('@admin/users/edit.html.twig', $this->buildFormViewData($user, $form, $formOptions));
                }

                $this->addFlash('success', sprintf('El usuario "%s" se actualizó correctamente.', $user->getNickname()));

                return $this->redirectToRoute('app_backend_users');
            }
        }

        return $this->render('@admin/users/edit.html.twig', $this->buildFormViewData($user, $form, $formOptions));
    }

    #[Route('/profile', name: 'app_backend_user_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $countryOptions = $this->buildCountryOptions();

        $infoForm = $this->createForm(UserProfileInfoType::class, $user);
        $passwordForm = $this->createForm(UserProfilePasswordType::class, null, [
            'user' => $user,
            'password_hasher' => $passwordHasher,
        ]);

        $infoForm->handleRequest($request);
        $passwordForm->handleRequest($request);

        if ($infoForm->isSubmitted()) {
            if (!$infoForm->isValid()) {
                $this->flashFormValidationErrors($infoForm);

                return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
                    $user,
                    $infoForm,
                    $passwordForm,
                    $countryOptions,
                    true,
                    false,
                ));
            }

            if (
                UserStatus::UncompleteProfileInfo === $user->getStatus()
                && $user->hasCompleteProfileContactInfo()
            ) {
                $user->setStatus(UserStatus::Active);
            }

            try {
                $entityManager->flush();
            } catch (Throwable) {
                $this->addFlash('error', 'No se pudo actualizar tu perfil. Inténtelo de nuevo.');

                return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
                    $user,
                    $infoForm,
                    $passwordForm,
                    $countryOptions,
                    true,
                    false,
                ));
            }

            $this->addFlash('success', 'Tu información personal se actualizó correctamente.');

            return $this->redirectToRoute('app_backend_user_profile');
        }

        if ($passwordForm->isSubmitted()) {
            if (!$passwordForm->isValid()) {
                $this->flashFormValidationErrors($passwordForm);

                return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
                    $user,
                    $infoForm,
                    $passwordForm,
                    $countryOptions,
                    false,
                    true,
                ));
            }

            $plainPassword = $passwordForm->get('plainPassword')->getData();
            if (!is_string($plainPassword) || '' === $plainPassword) {
                $this->flashFormValidationErrors($passwordForm);

                return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
                    $user,
                    $infoForm,
                    $passwordForm,
                    $countryOptions,
                    false,
                    true,
                ));
            }

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setPasswordUpdatedAt(new DateTime());

            try {
                $entityManager->flush();
            } catch (Throwable) {
                $this->addFlash('error', 'No se pudo actualizar tu contraseña. Inténtelo de nuevo.');

                return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
                    $user,
                    $infoForm,
                    $passwordForm,
                    $countryOptions,
                    false,
                    true,
                ));
            }

            $this->addFlash('success', 'Tu contraseña se actualizó correctamente.');

            return $this->redirectToRoute('app_backend_user_profile');
        }

        return $this->render('@admin/users/profile/index.html.twig', $this->buildProfileViewData(
            $user,
            $infoForm,
            $passwordForm,
            $countryOptions,
            false,
            false,
        ));
    }

    /**
     * @return array{
     *     show_is_hidden: bool,
     *     assignable_roles: list<UserRole>,
     *     default_roles?: list<UserRole>
     * }
     */
    private function buildFormOptions(User $editor, UserManagementPolicy $policy, bool $isCreate): array
    {
        $options = [
            'show_is_hidden' => $this->isGranted('ROLE_DEVELOPER'),
            'assignable_roles' => $policy->getAssignableRoles($editor),
        ];

        if ($isCreate) {
            $options['default_roles'] = $policy->getDefaultRoles($editor);
        }

        return $options;
    }

    /**
     * @param array{show_is_hidden: bool, assignable_roles: list<UserRole>} $formOptions
     * @param FormInterface<User>                                           $form
     *
     * @return array<string, mixed>
     */
    private function buildFormViewData(User $user, FormInterface $form, array $formOptions): array
    {
        $roleOptions = [];
        foreach ($formOptions['assignable_roles'] as $role) {
            $roleOptions[$role->value] = $role->label();
        }

        $statusOptions = [];
        foreach (UserStatus::cases() as $status) {
            $statusOptions[$status->value] = $status->label();
        }

        $countryOptions = [];
        foreach (AmericasCountryCodes::formChoices() as $label => $code) {
            $countryOptions[sprintf('%d', $code)] = $label;
        }

        return [
            'user' => $user,
            'form' => $form,
            'role_options' => $roleOptions,
            'status_options' => $statusOptions,
            'country_options' => $countryOptions,
            'show_is_hidden' => $formOptions['show_is_hidden'],
            'active_menu' => 'user_profile',
            'active_page' => 'user_form',
        ];
    }

    /**
     * @param FormInterface<User>   $infoForm
     * @param FormInterface<null>   $passwordForm
     * @param array<string, string> $countryOptions
     *
     * @return array<string, mixed>
     */
    private function buildProfileViewData(
        User $user,
        FormInterface $infoForm,
        FormInterface $passwordForm,
        array $countryOptions,
        bool $openInfoModal,
        bool $openPasswordModal,
    ): array {
        return [
            'user' => $user,
            'info_form' => $infoForm,
            'password_form' => $passwordForm,
            'country_options' => $countryOptions,
            'open_info_modal' => $openInfoModal,
            'open_password_modal' => $openPasswordModal,
            'active_menu' => 'auth',
            'active_page' => 'user_profile'
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCountryOptions(): array
    {
        $choices = AmericasCountryCodes::formChoices();
        $labels = array_keys($choices);
        $codes = array_map(
            static fn (int $code): string => (string) $code,
            array_values($choices),
        );

        /** @var array<string, string> $countryOptions */
        $countryOptions = array_combine($codes, $labels);

        return $countryOptions;
    }
}
