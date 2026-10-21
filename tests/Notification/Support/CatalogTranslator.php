<?php

declare(strict_types=1);

namespace App\Tests\Notification\Support;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

use function dirname;

final class CatalogTranslator
{
    public static function create(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('yaml', new YamlFileLoader());

        $directory = dirname(__DIR__, 3) . '/translations';

        foreach (['es', 'en', 'pt', 'fr'] as $locale) {
            $translator->addResource(
                'yaml',
                $directory . '/messages+intl-icu.' . $locale . '.yaml',
                $locale,
                'messages+intl-icu',
            );
        }

        return $translator;
    }
}
