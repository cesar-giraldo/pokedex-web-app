<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'component_multi_select',
    template: '@admin/components/multi_select_component.html.twig'
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
