<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Repository\PokemonRepository;
use App\Repository\PokemonTypeRepository;
use App\Service\PokeAPI\PokeAPIClient;
use App\Service\PokeAPI\PokemonDetails;
use App\Service\PokeAPI\PokemonTypeDetails;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

#[AsCommand(
    name: 'search-store-pokemons',
    description: 'Allows you to fetch a list of pokemons from the Poke API and store them in the database',
)]
class SearchStorePokemonsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PokemonRepository $pokemonRepository,
        private PokemonTypeRepository $pokemonTypeRepository,
        private PokeAPIClient $pokeApi,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'limit',
                InputArgument::OPTIONAL,
                'Number of Pokemons to fetch from the API',
                '5'       // Default value
            )
            ->addOption(
                'write',
                'w',
                InputOption::VALUE_OPTIONAL,
                'If this option is not set to true, the command will not store anything in the database',
                'false', // Default value, expects 'true' or 'false'
                ['true', 'false']
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info(' ------------ STARTING SCRIPT EXECUTION ------------ ');

        $limit = (int) $input->getArgument('limit');

        if ($limit) {
            $io->note(sprintf('Limit number of pokemons to fetch from the API: %s', $limit));
        }

        $writeInDatabase = false;
        if ('true' === $input->getOption('write')) {
            $writeInDatabase = true;
            $io->note('Write mode enabled, changes will be applied to the database');
        } else {
            $io->note('DRY RUN mode, NO changes will be applied to the database');
        }

        // Number of pokemons in the database before the command execution
        $offset = $this->pokemonRepository->count([]);

        $io->info(sprintf('Number of pokemons in the DB before command execution: %d', $offset));

        $pokemons = $this->pokeApi->listPokemons($limit, $offset);
        $totalPokemonsAdded = 0;
        $existingPokemonsCount = 0;
        $errorsCount = 0;

        foreach ($pokemons as $pokemonData) {
            $name = $pokemonData['name'];

            if ($writeInDatabase) {
                // Check if the pokemon already exists in the database
                $existingPokemon = $this->pokemonRepository->findOneByName($name);
                if ($existingPokemon) {
                    $io->error(sprintf('Pokemon "%s" already exists in the database, skipping...', $name));
                    ++$existingPokemonsCount;
                    continue;
                }

                // Get pokemon details from the API
                $pokemonDetails = $this->pokeApi->getPokemonByName($name);
                if (!$pokemonDetails instanceof PokemonDetails) {
                    $io->error(sprintf('Failed to fetch details for Pokemon "%s", skipping...', $name));
                    ++$errorsCount;
                    continue;
                }

                // Get or create pokemon type in the database
                $pokemonType = $this->createPokemonType($pokemonDetails);
                if (!$pokemonType instanceof PokemonType) {
                    $io->error(sprintf('Failed to determine type for Pokemon "%s", skipping...', $name));
                    ++$errorsCount;
                    continue;
                }

                $pokemon = $this->createPokemonEntityFromApiData($pokemonDetails, $pokemonType);
                $this->entityManager->flush();

                ++$totalPokemonsAdded;
                $io->comment(sprintf('Pokemon "%s" of type "%s" has been saved to the database.', $pokemon->getName(), $pokemonType->getName()));
            } else {
                $io->comment(sprintf('DRY RUN: Pokemon "%s" would be added to the database.', $name));
            }
        }

        if ($writeInDatabase) {
            $io->info(sprintf('Total Pokemons added to the database: %d', $totalPokemonsAdded));
            $io->info(sprintf('Total existing Pokemons skipped: %d', $existingPokemonsCount));
            $io->info(sprintf('Total errors encountered: %d', $errorsCount));
        } else {
            $io->info('DRY RUN mode, no Pokemons were added to the database.');
        }

        $io->success(' ------------ FINISHED SCRIPT EXECUTION ------------ ');

        return Command::SUCCESS;
    }

    private function createPokemonType(PokemonDetails $pokemonDetails): ?PokemonType
    {
        // check the count or array types
        if (empty($pokemonDetails->types)) {
            return null;
        }

        $typeName = $pokemonDetails->types[0]['type']['name'] ?? 'unknown';

        // check if pokemon type already exists in the database
        $existingType = $this->pokemonTypeRepository->findOneByName($typeName);
        if ($existingType) {
            return $existingType;
        }

        // get pokemon type details from the API
        $typeDetails = $this->pokeApi->getPokemonTypeByName($typeName);
        if (!$typeDetails instanceof PokemonTypeDetails) {
            return null;
        }

        // create and save the pokemon type in the database
        $pokemonType = new PokemonType();
        $pokemonType->setName($typeDetails->name);
        $pokemonType->setGeneration($typeDetails->generation['name']);

        if ($typeDetails->sprites['generation-iii']) {
            $pokemonType->setSprite($typeDetails->sprites['generation-iii']['colosseum']['name_icon'] ?? '');
        }

        $this->entityManager->persist($pokemonType);

        return $pokemonType;
    }

    /**
     * Filters stats by name and returns the base stat value.
     *
     * @param array<int, array{base_stat: int, effort: int, stat: array{name: string, url: string}}> $stats The list of stats
     * @param string                                                                                 $name  The name of the stat to filter
     *
     * @return int The base stat value
     */
    private function filterStats(array $stats, string $name): int
    {
        $stat = array_filter($stats, static function ($st) use ($name) {
            return $name === $st['stat']['name'];
        });
        if (!empty($stat)) {
            // array_filter preserva las claves, así que tomamos el primer elemento
            $first = reset($stat);

            return (int) $first['base_stat'];
        }

        return 0;
    }

    private function createPokemonEntityFromApiData(PokemonDetails $pokemonDetails, PokemonType $pokemonType): Pokemon
    {
        $pokemon = new Pokemon();
        $pokemon->setName($pokemonDetails->name);
        $pokemon->setType($pokemonType);

        $pokemon->setListOrder($pokemonDetails->order);
        $pokemon->setHeight($pokemonDetails->height);
        $pokemon->setWeight($pokemonDetails->weight);

        /**
         * Filter and set stats.
         */
        $pokemon->setHealthPoints($this->filterStats($pokemonDetails->stats, 'hp'));
        $pokemon->setAttack($this->filterStats($pokemonDetails->stats, 'attack'));
        $pokemon->setDefense($this->filterStats($pokemonDetails->stats, 'defense'));
        $pokemon->setSpeed($this->filterStats($pokemonDetails->stats, 'speed'));

        $pokemon->setSpriteFront($pokemonDetails->sprites['front_default'] ?? '');
        $pokemon->setSpriteBack($pokemonDetails->sprites['back_default'] ?? '');

        $this->entityManager->persist($pokemon);

        return $pokemon;
    }
}
