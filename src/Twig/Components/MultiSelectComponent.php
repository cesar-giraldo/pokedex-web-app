<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'component_multi_select',
    template: 'components/MultiSelectComponent.html.twig'
)]
final class MultiSelectComponent
{
    public string $title = 'Selector Multiple';
    public string $placeholder = 'Seleccione...';

    /**
     * @var array<string, string>
     */
    public array $options = [];
}
