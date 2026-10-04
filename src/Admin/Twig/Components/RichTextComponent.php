<?php

declare(strict_types=1);

namespace App\Admin\Twig\Components;

use App\Admin\Twig\Components\Concerns\NormalizesComponentError;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Editor de texto enriquecido reutilizable para formularios del admin.
 *
 * <twig:component_rich_text
 *     label="Contenido"
 *     name="content"
 *     id="content"
 *     value=""
 *     :required="true"
 * />
 */
#[AsTwigComponent(
    name: 'component_rich_text',
    template: '@admin/components/rich_text_component.html.twig'
)]
final class RichTextComponent
{
    use NormalizesComponentError;

    public string $label = '';

    public string $name = '';

    public string $id = '';

    public string $value = '';

    public bool $required = false;

    public bool $disabled = false;

    public ?string $error = null;

    public ?string $help = null;

    public function mount(): void
    {
        if ('' === $this->id) {
            $this->id = 'rich-text-' . bin2hex(random_bytes(4));
        }

        if ('' === $this->name) {
            $this->name = $this->id;
        }
    }

    #[ExposeInTemplate('labelClasses')]
    public function getLabelClasses(): string
    {
        if ($this->disabled) {
            return 'mb-1.5 block text-sm font-medium text-gray-300 dark:text-white/15';
        }

        return 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    }
}
