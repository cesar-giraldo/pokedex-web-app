# Alta pública con Google

El sitio público ofrece «Regístrate con Google» en el registro y «Entra con Google» en el login. Los dos botones usan el mismo recorrido. Google redirige el navegador a una sola dirección, sin idioma en la ruta:

`https://localhost/connect/google/check`

El idioma se guarda al pulsar el botón. La aplicación comprueba el ID token en el servidor. Si el correo es nuevo y Google lo marcó como verificado, se crea la cuenta con el perfil incompleto y sin código de confirmación. Si el correo ya existe y Google lo verificó, se vincula esa cuenta.

Esta guía deja la consola lista para probar en local. El client id y el secret viven en `.env.local`, nunca en el repositorio.

## Qué dejar sin tocar

- No pulses **Publish app** ni pases la pantalla de consentimiento a producción. En modo Pruebas solo entran los Gmail que agregues como usuarios de prueba.
- No pegues el client secret en `.env`, en un commit, en un chat ni en la documentación. `.env` deja las dos variables vacías. El valor real va en `.env.local`.
- No registres una URI con idioma (`/es/connect/google/check`) ni con una barra final distinta. Tiene que ser exactamente `https://localhost/connect/google/check`.
- No agregues otro origen ni otra URI «por si acaso» mientras pruebas en local. Una diferencia de una letra produce `redirect_uri_mismatch`.
- No uses el tipo de cliente Escritorio, Android ni iOS. La credencial es de aplicación web.
- No actives scopes distintos de `openid`, `email` y `profile`.

El certificado autofirmado de `https://localhost` alcanza. Google no llama a tu máquina: redirige el navegador, y el navegador ya confía en ese certificado.

## 1. Elegir el proyecto

1. Entra a [Google Cloud Console](https://console.cloud.google.com/) con la cuenta que va a administrar la credencial.
2. Arriba, abre el selector de proyectos.
3. Elige el proyecto existente o pulsa **New project**. Un nombre como `pokedex-local` basta.
4. Espera a que el proyecto quede seleccionado. El resto de los pasos ocurren dentro de ese proyecto.

## 2. Pantalla de consentimiento

1. Menú **APIs y servicios** → **Pantalla de consentimiento de OAuth**. Si te pide el tipo de usuario, elige **Externo** y continúa.
2. Completa el nombre de la aplicación y un correo de asistencia. El resto de los campos opcionales pueden quedar vacíos en local.
3. En **Scopes** / **Ámbitos**, agrega solo estos tres: `openid`, `email` y `profile`. En la consola a veces aparecen como `.../auth/userinfo.email` y `.../auth/userinfo.profile`; `openid` va junto a ellos.
4. Deja el estado de publicación en **Pruebas** (**Testing**). No publiques la aplicación.
5. En **Usuarios de prueba** (**Test users**), agrega el Gmail con el que vas a entrar. Mientras la app siga en pruebas, ninguna otra cuenta puede completar el flujo.
6. Guarda.

## 3. Crear la credencial

1. **APIs y servicios** → **Credenciales** → **Crear credenciales** → **ID de cliente de OAuth**.
2. Tipo de aplicación: **Aplicación web**.
3. Nombre: `pokedex-localhost` o el que prefieras. Solo se ve en la consola.
4. **Orígenes autorizados de JavaScript**: `https://localhost`. Sin puerto, sin ruta y sin barra final.
5. **URI de redirección autorizados**: una sola línea, exactamente `https://localhost/connect/google/check`.
6. Crea la credencial. Copia el **Client ID** y el **Client secret**. El secret solo se muestra completo en este momento; si lo pierdes, genera otro desde la misma credencial.

## 4. Guardar las variables

En la raíz del proyecto, `.env` ya declara las variables vacías:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
```

No las rellenes ahí. Crea o edita `.env.local` y pega los valores de la consola:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=tu-client-id.apps.googleusercontent.com
GOOGLE_OAUTH_CLIENT_SECRET=tu-client-secret
```

`.env.local` no se commitea.

Limpia la caché para que el contenedor lea esos valores:

```bash
docker compose exec php php bin/console cache:clear
```

## 5. Comprobar que quedó bien

1. Abre `https://localhost/es/register`.
2. El botón **Regístrate con Google** está encima del formulario, a todo el ancho. Debajo aparece el separador «o» y el formulario manual sigue igual.
3. Pulsa el botón. Google pide consentimiento. Usa el Gmail que agregaste como usuario de prueba.
4. Al aceptar, el navegador vuelve a `https://localhost/connect/google/check` y la aplicación te deja en el perfil, con la sesión iniciada. No aparece la pantalla del código de 6 dígitos.
5. Cierra la sesión y abre `https://localhost/es/login`. **Entra con Google** debe devolverte a la misma cuenta.

Si Google responde `redirect_uri_mismatch`, la URI de la consola y la de la aplicación no son idénticas. Compara esquema (`https`), host (`localhost`), ruta (`/connect/google/check`) y la ausencia de barra final o de prefijo de idioma.
