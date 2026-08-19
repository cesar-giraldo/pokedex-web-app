<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\AdminPaginatorTrait;
use App\Admin\Data\AmericasCountryCodes;
use App\Admin\Form\SearchUserType;
use App\Admin\Form\UserCreateType;
use App\Admin\Form\UserEditType;
use App\Admin\Service\UserManagementPolicy;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Repository\UserRepository;
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
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    use AdminPaginatorTrait;

    #[Route('/users', name: 'app_backend_users')]
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
            } else {
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
        }

        return $this->render('@admin/users/new.html.twig', $this->buildFormViewData($user, $form, $formOptions));
    }

    #[Route('/users/{id}/edit', name: 'app_backend_user_edit', methods: ['GET', 'POST'])]
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
            return $this->redirectToRoute('app_design_user_profile');
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
            $countryOptions[(string) $code] = $label;
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
}
