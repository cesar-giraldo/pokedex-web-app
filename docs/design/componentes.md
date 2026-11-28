# Inventario de interfaz

Úsalo antes de crear markup. El sitio público y el admin no comparten componentes ni tokens.

| Contexto | Tema | Invocación |
| --- | --- | --- |
| Público | `assets/web/styles/site.css` | `<twig:web_*>` |
| Admin | `assets/admin/styles/app.css` | `<twig:component_*>` |

Las props y el ejemplo de uso están en el PHP de cada componente, bajo `src/Web/Twig/Components/` y `src/Admin/Twig/Components/`.

## Sitio público

El estilo nuevo va en utilidades de Tailwind sobre el elemento (`bg-brand`, `text-ink`, `rounded-2xl`). Las clases `field`, `btn`, `alert-*` y similares que aún viven en `site.css` son el estilo actual de estos componentes; al editar uno, ese estilo pasa a utilidades y la clase se elimina cuando ya no tenga usos.

| Componente | Cuándo usarlo | Estados que ya cubre |
| --- | --- | --- |
| `web_text_input` | Texto de una línea: nombre, email u otro `type` HTML | requerido, deshabilitado, error, ayuda, límites de longitud |
| `web_password_input` | Contraseña con mostrar y ocultar | requerido, deshabilitado, error, ayuda |
| `web_file_input` | Un archivo con `accept` | requerido, deshabilitado, error, ayuda |
| `web_select` | Una opción entre una lista corta | placeholder, seleccionado, requerido, deshabilitado, error, ayuda |
| `web_checkbox` | Aceptación o preferencia booleana | marcado, requerido, deshabilitado, error |
| `web_toggle` | Interruptor de una preferencia (`role="switch"`) | marcado, deshabilitado, error, ayuda |
| `web_submit_button` | Envío de un formulario público | deshabilitado; al enviar pasa a la etiqueta de proceso |
| `web_alert` | Aviso de página o de formulario. `message` vacío no renderiza nada | `error`, `success`, `warning`, `status`; se puede cerrar |

Ejemplo de referencia de página: `templates/web/pokemon/show.html.twig`. Formularios de cuenta: `templates/web/account/profile.html.twig`.

Estos partials forman el cromo o un bloque de una página concreta. La base ya incluye cabecera, navegación y pie.

| Partial | Uso |
| --- | --- |
| `templates/web/partials/_header.html.twig` | Cabecera pública |
| `templates/web/partials/_nav.html.twig` | Navegación |
| `templates/web/partials/_footer.html.twig` | Pie |
| `templates/web/partials/_account_menu.html.twig` | Menú de la cuenta autenticada |
| `templates/web/partials/_language_switcher.html.twig` | Cambio de idioma |
| `templates/web/partials/_breadcrumb.html.twig` | Migas |
| `templates/web/partials/_header_notifications.html.twig` | Avisos del encabezado |
| `templates/web/partials/_impersonation_banner.html.twig` | Sesión suplantada |
| `templates/web/partials/_favorite_dialog.html.twig` | Diálogo de favoritos |
| `templates/web/partials/_image_gallery_dialog.html.twig` | Galería de imágenes |
| `templates/web/pokemon/_card.html.twig` | Tarjeta de un Pokémon en el listado |
| `templates/web/pokemon/_catalog.html.twig` | Listado del catálogo |
| `templates/web/pokemon/_favorite_button.html.twig` | Acción de favorito sobre un Pokémon |
| `templates/web/auth/_google.html.twig` | Botón de alta con Google |
| `templates/web/error/_401_body.html.twig`, `_403_body.html.twig`, `_404_body.html.twig`, `_500_body.html.twig` | Cuerpo de los errores públicos |

`templates/web/components/pokemon_internal_search.html.twig` y `pokemon_external_search.html.twig` son buscadores en vivo. No los uses como patrón visual.

## Admin

Los controles de formulario comparten etiqueta, asterisco de obligatorio, deshabilitado y error de servidor. Varios añaden validación en el cliente mediante su controlador Stimulus.

| Componente | Cuándo usarlo | Extra respecto al patrón común |
| --- | --- | --- |
| `component_text_input` | Texto de una línea | patrón, mayúsculas, minúsculas, solo números, icono, longitudes |
| `component_email_input` | Email | validación de formato |
| `component_url_input` | URL | validación de formato |
| `component_phone_input` | Teléfono | formato de teléfono |
| `component_password_input` | Contraseña | mostrar y ocultar |
| `component_textarea` | Texto de varias líneas | |
| `component_rich_text` | Texto con formato | editor |
| `component_date_picker` | Fecha | selector |
| `component_time_picker` | Hora | selector |
| `component_file_input` | Un archivo | |
| `component_dropzone` | Uno o varios archivos arrastrados | zona de suelta |
| `component_single_select` | Una opción de una lista | |
| `component_multi_select` | Varias opciones | |
| `component_radio` | Una opción visible entre pocas | |
| `component_checkbox` | Booleano | |
| `component_toggle_switch` | Interruptor | |
| `component_submit_button` | Envío de un formulario del admin | etiqueta de proceso y deshabilitado |
| `component_confirm_dialog` | Confirmar una acción destructiva | `confirmTone` (`danger` por defecto), etiquetas de cancelar y confirmar |
| `component_alert_success`, `component_alert_warning`, `component_alert_error`, `component_alert_info` | Notificación del panel | cierre y ocultado automático (`autoHideDelay`) |

La referencia de composición es `templates/admin/users/_form.html.twig`.

| Partial | Uso |
| --- | --- |
| `templates/admin/partials/_sidebar.html.twig` | Menú lateral, ya incluido por la base |
| `templates/admin/partials/_header.html.twig` | Cabecera del panel |
| `templates/admin/partials/_overlay.html.twig` | Capa del menú móvil |
| `templates/admin/partials/_preloader.html.twig` | Precarga |
| `templates/admin/partials/_impersonation_banner.html.twig` | Sesión suplantada |
| `templates/admin/partials/_quick_access_card.html.twig` | Tarjeta de acceso en el tablero |
| `templates/admin/partials/_image_lightbox.html.twig` | Visor de imagen |
| `templates/admin/partials/_user_profile_avatar.html.twig` | Avatar del operador |
| `templates/admin/partials/paginator/_paginator.html.twig` | Paginación de un listado |
| `templates/admin/partials/alert/_general_notifications.html.twig` | Avisos globales del panel |

Los partials `templates/admin/partials/badge/` y `templates/admin/partials/alert/_alert_*.html.twig` son muestras del UI Kit, con texto fijo. Copia sus clases; no los incluyas para datos reales. Las notificaciones de producto van con `<twig:component_alert_*>`.

### UI Kit

Rutas del catálogo, bajo `/admin/ui-kit`. Sirven para mirar la pieza, no para incluirlas.

| Ruta | Qué enseña |
| --- | --- |
| `/admin/ui-kit/index` | Índice del kit |
| `/admin/ui-kit/forms` | Formulario compuesto |
| `/admin/ui-kit/tables` | Tabla |
| `/admin/ui-kit/profile` | Perfil con modales |
| `/admin/ui-kit/blank-page` | Página en blanco sobre la base del admin |
| `/admin/ui-kit/ui-elements/alerts` | Alertas |
| `/admin/ui-kit/ui-elements/badge` | Badges |
| `/admin/ui-kit/ui-elements/buttons` | Botones |
| `/admin/ui-kit/ui-elements/images` | Imágenes |
| `/admin/ui-kit/ui-elements/videos` | Vídeos |
| `/admin/ui-kit/charts/line-chart` | Gráfico de líneas |
| `/admin/ui-kit/charts/bar-chart` | Gráfico de barras |
| `/admin/ui-kit/charts/pie-chart` | Gráfico circular |
| `/admin/ui-kit/auth/sign-in` | Acceso |
| `/admin/ui-kit/auth/sign-up` | Registro |
| `/admin/ui-kit/page-not-found` | 404 |
| `/admin/ui-kit/server-error` | 500 |
