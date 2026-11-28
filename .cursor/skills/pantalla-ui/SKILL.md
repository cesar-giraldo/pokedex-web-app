---
name: pantalla-ui
description: Maqueta o revisa pantallas y componentes del sitio público o del admin en este proyecto Symfony, reutilizando los Twig components, los tokens de Tailwind 4 y Stimulus. Usar cuando el usuario pida diseñar, maquetar, construir, ajustar o revisar una pantalla, página, layout, formulario, diálogo, componente visual o interfaz en templates/ o assets/.
---

# Pantalla UI

Construye la interfaz en el contexto que corresponde y comprueba el resultado en el navegador antes de cerrar la tarea.

## Elegir el contexto

| Si la ruta o la plantilla vive en… | Lee |
| --- | --- |
| `templates/web/`, `assets/web/`, `assets/controllers/web/` | `assets/web/styles/site.css` y la sección pública de `docs/design/componentes.md` |
| `templates/admin/`, `assets/admin/`, `assets/controllers/admin/` | `assets/admin/styles/app.css` y la sección admin de `docs/design/componentes.md` |

No mezcles tokens, clases ni componentes de un contexto en el otro. El cromo de `templates/*/base.html.twig` ya incluye cabecera, navegación y avisos: no los vuelvas a insertar en la página.

## Estilos

Escribe el estilo con utilidades de Tailwind en el elemento. Los tokens salen del `@theme` del contexto (`bg-brand`, `text-ink` en la web; `bg-brand-500`, `text-gray-800`, `dark:` en el admin).

`site.css` y `app.css` se reservan para la entrada de Tailwind (`@import`, `@source`, `@theme`, `@font-face`, `@custom-variant`) y para un selector que ninguna utilidad pueda expresar. Una clase nueva en esas hojas, o un envoltorio `@apply`, queda fuera.

Si el markup que tocas usa una clase propia (`.btn`, `.field`, `.alert-*` u otra definida en la hoja), pasa ese estilo a utilidades en el elemento y elimina la clase cuando ya no tenga usos. El CSS que esta tarea no toca se deja igual.

## Orden de trabajo

1. Busca en el inventario una pieza que cubra el control o el bloque. Úsala con `<twig:web_*>` o `<twig:component_*>`. Crea markup nuevo solo cuando ninguna pieza sirva.
2. Maqueta la plantilla del contexto. Incluye los estados vacío, error, carga y deshabilitado que esa pantalla puede mostrar.
3. Si cambias un controlador Stimulus o `importmap.php`, ejecuta desde la raíz:

```bash
docker compose exec php composer assets:compile
```

4. Abre la ruta en el navegador y recorre el flujo como un usuario: escribir, enviar, provocar el error y ver el estado vacío. Repite en un ancho de escritorio y en uno móvil. En admin, repite con la clase `.dark` presente.
5. Corrige espaciado, contraste, foco visible y orden de encabezados antes de dar la tarea por terminada. Un pantallazo sin recorrer el flujo no cierra el paso 4.

## Al revisar una pantalla existente

Compara la plantilla con el inventario y con el `@theme` del contexto. Señala un control reinventado, un color fuera de `@theme`, una clase propia que las utilidades ya pueden expresar y un estado que la pantalla puede alcanzar y no muestra.
