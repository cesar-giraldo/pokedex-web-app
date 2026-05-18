<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\PokeAPIClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

use function is_array;

#[AsLiveComponent()]
final class PokemonExternalSearch
{
    use DefaultActionTrait;

    public function __construct(
        private PokeAPIClient $pokeApi
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public ?array $pokemon = null;

    #[LiveAction]
    public function search(): void
    {
        $result = $this->pokeApi->getPokemonByName($this->name);
        if (is_array($result)) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
