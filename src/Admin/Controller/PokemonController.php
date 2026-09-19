<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\AdminPaginatorTrait;
use App\Admin\Form\PokemonEditType;
use App\Admin\Form\PokemonImageUploadType;
use App\Admin\Form\SearchPokemonType;
use App\Admin\Service\Excel\ExcelGenerationException;
use App\Admin\Service\Excel\PokemonListExcelExporter;
use App\Admin\Service\Pdf\PdfGenerationException;
use App\Admin\Service\Pdf\PokemonListPdfExporter;
use App\Admin\Service\Storage\AllowedImageTypes;
use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Admin\Service\Storage\PokemonImageUploadService;
use App\Entity\Pokemon;
use App\Repository\PokemonRepository;
use App\Repository\PokemonTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;
use function sprintf;

#[Route('/admin')]
final class PokemonController extends AbstractController
{
    use AdminPaginatorTrait;

    #[Route('/pokemons', name: 'app_backend_pokemons')]
    public function pokemons(PokemonRepository $pokemonRepository, Request $request): Response
    {
        $form = $this->createForm(SearchPokemonType::class);
        $form->handleRequest($request);

        $term = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $term = $data['q'] ?? null;
        }

        $sort = $request->query->get('sort', 'p.listOrder');
        $direction = $request->query->get('direction', 'asc');
        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder(
            $term,
            $sort,
            $direction,
            ['includeHidden' => true],
        );

        $pagination = $this->getPagination($queryBuilder, $request);

        return $this->render('@admin/pokemons/index.html.twig', [
            'controller_name' => 'PokemonController',
            'active_menu' => 'pokemon_list',
            'active_page' => 'pokemon_list',
            'search_form' => $form->createView(),
            'current_sort' => $sort,
            'current_direction' => $direction,
            'search_term' => $term,
            ...$pagination,
        ]);
    }

    #[Route('/pokemons/export/pdf', name: 'app_backend_pokemons_export_pdf', methods: ['GET'])]
    public function exportPdf(
        PokemonRepository $pokemonRepository,
        PokemonListPdfExporter $pdfExporter,
        Request $request,
    ): Response {
        $term = $request->query->get('q');
        $term = is_string($term) && '' !== $term ? $term : null;

        $sort = $request->query->get('sort', 'p.listOrder');
        $direction = $request->query->get('direction', 'asc');

        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder(
            $term,
            $sort,
            $direction,
            ['includeHidden' => true],
        );

        $pagination = $this->getPagination($queryBuilder, $request);
        $pagerfanta = $pagination['entities'];

        /** @var list<Pokemon> $pokemons */
        $pokemons = iterator_to_array($pagerfanta->getCurrentPageResults());

        $repeatHeaderFooter = true;

        try {
            $pdfContent = $pdfExporter->export(
                $pokemons,
                $term,
                $sort,
                $direction,
                [
                    'current_page' => $pagerfanta->getCurrentPage(),
                    'total_pages' => $pagerfanta->getNbPages(),
                    'total_results' => $pagerfanta->getNbResults(),
                    'max_per_page' => $pagerfanta->getMaxPerPage(),
                ],
                $repeatHeaderFooter,
            );
        } catch (PdfGenerationException) {
            $this->addFlash('error', 'No se pudo generar el PDF. Verifique que Gotenberg esté disponible e inténtelo de nuevo.');

            return $this->redirectToRoute('app_backend_pokemons', $request->query->all());
        }

        $filename = sprintf(
            'pokemons-%s-page-%d-of-%d.pdf',
            date('Y-m-d'),
            $pagerfanta->getCurrentPage(),
            $pagerfanta->getNbPages(),
        );

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/pokemons/export/excel', name: 'app_backend_pokemons_export_excel', methods: ['GET'])]
    public function exportExcel(
        PokemonRepository $pokemonRepository,
        PokemonListExcelExporter $excelExporter,
        Request $request,
    ): Response {
        $term = $request->query->get('q');
        $term = is_string($term) && '' !== $term ? $term : null;

        $sort = $request->query->get('sort', 'p.listOrder');
        $direction = $request->query->get('direction', 'asc');

        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder(
            $term,
            $sort,
            $direction,
            ['includeHidden' => true],
        );

        $pagination = $this->getPagination($queryBuilder, $request);
        $pagerfanta = $pagination['entities'];

        /** @var list<Pokemon> $pokemons */
        $pokemons = iterator_to_array($pagerfanta->getCurrentPageResults());

        try {
            $excelContent = $excelExporter->export(
                $pokemons,
                $term,
                $sort,
                $direction,
                [
                    'current_page' => $pagerfanta->getCurrentPage(),
                    'total_pages' => $pagerfanta->getNbPages(),
                    'total_results' => $pagerfanta->getNbResults(),
                    'max_per_page' => $pagerfanta->getMaxPerPage(),
                ],
            );
        } catch (ExcelGenerationException) {
            $this->addFlash('error', 'No se pudo generar el archivo Excel. Inténtelo de nuevo.');

            return $this->redirectToRoute('app_backend_pokemons', $request->query->all());
        }

        $filename = sprintf(
            'pokemons-%s-page-%d-of-%d.xlsx',
            date('Y-m-d'),
            $pagerfanta->getCurrentPage(),
            $pagerfanta->getNbPages(),
        );

        return new Response($excelContent, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/pokemons/{id}/edit', name: 'app_backend_pokemon_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(Pokemon $pokemon): Response
    {
        return $this->redirectToRoute('app_backend_pokemon_edit_basic', ['id' => $pokemon->getId()], Response::HTTP_FOUND);
    }

    #[Route('/pokemons/{id}/edit/basic', name: 'app_backend_pokemon_edit_basic', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editBasic(
        Pokemon $pokemon,
        Request $request,
        EntityManagerInterface $entityManager,
        PokemonTypeRepository $pokemonTypeRepository,
    ): Response {
        $form = $this->createForm(PokemonEditType::class, $pokemon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('El Pokémon "%s" se actualizó correctamente.', $pokemon->getName()));

            return $this->redirectToRoute('app_backend_pokemons');
        }

        return $this->render('@admin/pokemons/edit.html.twig', $this->buildEditViewData(
            $pokemon,
            $form,
            $pokemonTypeRepository,
            'basic',
        ));
    }

    #[Route('/pokemons/{id}/edit/multimedia', name: 'app_backend_pokemon_edit_multimedia', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editMultimedia(
        Pokemon $pokemon,
        Request $request,
        PokemonTypeRepository $pokemonTypeRepository,
        PokemonImageUploadService $pokemonImageUploadService,
    ): Response {
        $form = $this->createForm(PokemonImageUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('image')->getData();
            $description = $form->get('description')->getData();
            $description = is_string($description) ? $description : null;

            if (!$uploadedFile instanceof UploadedFile) {
                $form->get('image')->addError(new FormError('Debes seleccionar una imagen.'));
            } else {
                try {
                    $pokemonImageUploadService->upload($pokemon, $uploadedFile, $description);
                    $this->addFlash('success', 'La imagen se subió correctamente.');

                    return $this->redirectToRoute('app_backend_pokemon_edit_multimedia', [
                        'id' => $pokemon->getId(),
                    ]);
                } catch (PokemonImageUploadException $exception) {
                    $form->get('image')->addError(new FormError($exception->getMessage()));
                    $this->addFlash('error', $exception->getMessage());
                }
            }
        }

        return $this->render('@admin/pokemons/edit.html.twig', $this->buildEditViewData(
            $pokemon,
            $form,
            $pokemonTypeRepository,
            'multimedia',
        ));
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return array{
     *     pokemon: Pokemon,
     *     form: FormInterface<mixed>,
     *     type_options: array<int|string, string>,
     *     active_menu: string,
     *     active_page: string,
     *     edit_tab: string,
     *     image_accept: string,
     *     image_max_size_kb: int
     * }
     */
    private function buildEditViewData(
        Pokemon $pokemon,
        FormInterface $form,
        PokemonTypeRepository $pokemonTypeRepository,
        string $editTab,
    ): array {
        $typeOptions = [];
        foreach ($pokemonTypeRepository->findAllOrderedByName() as $type) {
            $typeOptions[(string) $type->getId()] = $type->getName();
        }

        return [
            'pokemon' => $pokemon,
            'form' => $form,
            'type_options' => $typeOptions,
            'active_menu' => 'pokemon_list',
            'active_page' => 'pokemon_edit',
            'edit_tab' => $editTab,
            'image_accept' => AllowedImageTypes::ACCEPT_ATTRIBUTE,
            'image_max_size_kb' => AllowedImageTypes::MAX_SIZE_KB,
        ];
    }
}
