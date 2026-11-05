<?php

declare(strict_types=1);

/**
 * Catálogo sembrado por Version20261105150000.
 * Los padres van antes que los hijos. Una fila existente no se modifica.
 *
 * @return list<array{name: string, slug: string, sortOrder: string, parentSlug: ?string, description: ?string}>
 */
return [
    [
        'name' => 'Web Secure Area',
        'slug' => 'module_web_secure_area',
        'sortOrder' => '1.00',
        'parentSlug' => null,
        'description' => 'Si este módulo está habilitado, significa que tendremos habilitado el inicio de sesión para clientes finales de tipo \'user\'.',
    ],
    [
        'name' => 'Login con Google',
        'slug' => 'module_web_google_auth',
        'sortOrder' => '1.10',
        'parentSlug' => 'module_web_secure_area',
        'description' => 'Si este módulo está habilitado, tendremos habilitado el inicio de sesión mediante Google; de lo contrario, se debe ocultar esa opción.',
    ],
    [
        'name' => 'Recuperación de Contraseña',
        'slug' => 'module_web_password_recovery',
        'sortOrder' => '1.20',
        'parentSlug' => 'module_web_secure_area',
        'description' => 'Si este módulo está habilitado, se debe mostrar la opción de recuperación de contraseña para los usuarios de la web autenticada; de lo contrario, debe ocultarse.',
    ],
    [
        'name' => 'Mostrar Política de Cookies',
        'slug' => 'module_web_show_cookies_policy',
        'sortOrder' => '1.30',
        'parentSlug' => 'module_web_secure_area',
        'description' => 'Si este módulo está habilitado, en la web se deben mostrar el enlace y la página de acceso a la política de cookies.',
    ],
    [
        'name' => 'Admin Secure Area',
        'slug' => 'module_admin_secure_area',
        'sortOrder' => '2.00',
        'parentSlug' => null,
        'description' => 'Agrupa la recuperación de contraseña del admin, la administración de documentos legales y UI Kit. Si se deshabilita, esas funciones se ocultan y se deniega su acceso. El inicio de sesión y el resto del panel siguen disponibles.',
    ],
    [
        'name' => 'Recuperación de Contraseña',
        'slug' => 'module_admin_password_recovery',
        'sortOrder' => '2.10',
        'parentSlug' => 'module_admin_secure_area',
        'description' => 'Si este módulo está habilitado, se permite a los usuarios del admin ver y utilizar la recuperación de contraseña; en caso contrario, se debe ocultar.',
    ],
    [
        'name' => 'Administración de Documentos Legales',
        'slug' => 'module_admin_legal_documents_management',
        'sortOrder' => '2.20',
        'parentSlug' => 'module_admin_secure_area',
        'description' => 'Si este módulo está habilitado, se permite a los usuarios del admin que tengan los privilegios ver y acceder a la administración y edición de documentos legales.',
    ],
    [
        'name' => 'UI Kit',
        'slug' => 'module_admin_ui_kit',
        'sortOrder' => '2.30',
        'parentSlug' => 'module_admin_secure_area',
        'description' => 'Si este módulo está habilitado, en el admin se debe mostrar UI Kit, accesible para los usuarios cuyo rol tenga permiso para visualizarlo.',
    ],
    [
        'name' => 'Pokemons',
        'slug' => 'module_pokemons',
        'sortOrder' => '9.00',
        'parentSlug' => null,
        'description' => "Creación y listado de pokemones en el admin.\n\nListado de pokemones en la web.\n\nSi este módulo está deshabilitado, se debe seguir mostrando la página de inicio, pero sin el carrusel de pokemones.",
    ],
    [
        'name' => 'Pokemones Favoritos',
        'slug' => 'module_pokemons_favorites',
        'sortOrder' => '9.10',
        'parentSlug' => 'module_pokemons',
        'description' => null,
    ],
];
