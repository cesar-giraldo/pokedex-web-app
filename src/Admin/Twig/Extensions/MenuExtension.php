<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // Register the function that will be available in Twig
            new TwigFunction('get_menu_classes', [$this, 'getMenuClasses']),
        ];
    }

    /**
     * Calculate CSS classes for the menu based on the active menu and page.
     *
     * @param string      $activeMenu     Currently active menu in the session/request
     * @param string      $activePage     Currently active page in the session/request
     * @param string      $menuToEvaluate The menu to evaluate for active state
     * @param string|null $pageToEval     The page to evaluate for active state (optional
     *
     * @return array<string, string> Object with the css classes
     */
    public function getMenuClasses(
        string $activeMenu,
        string $activePage,
        string $menuToEvaluate,
        ?string $pageToEval = null
    ): array {
        // Default values (Inactive State)
        $classes = [
            'item_class' => 'menu-item-inactive',
            'item_icon_class' => 'menu-item-icon-inactive',
            'arrow_icon_class' => 'menu-item-arrow-inactive',
            'dropdown_class' => 'hidden',
            'dropdown_item_class' => 'menu-dropdown-item-inactive',
        ];

        if ($activeMenu === $menuToEvaluate) {
            $classes['item_class'] = 'menu-item-active';
            $classes['item_icon_class'] = 'menu-item-icon-active';
            $classes['arrow_icon_class'] = 'menu-item-arrow-active';
            $classes['dropdown_class'] = 'block';

            if ($pageToEval && $activePage === $pageToEval) {
                $classes['dropdown_item_class'] = 'menu-dropdown-item-active';
            }
        }

        return $classes;
    }
}
