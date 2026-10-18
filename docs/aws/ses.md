# Amazon SES para correos transaccionales

Amazon SES no ofrece un remitente temporal. En sandbox solo se puede enviar desde y hacia identidades verificadas. Un correo personal verificado sirve como remitente y como buzón de pruebas. Cuando exista un dominio, se sustituye esa identidad sin cambiar el código de la aplicación.

La aplicación envía por la API de SES (`ses+api`), con credenciales distintas de las de S3. En `dev`, el sobre de entrega se redirige a `MAILER_REDIRECT_TO`. El encabezado `To` sigue mostrando al destinatario original.

SES es regional. Una identidad creada en `us-east-1` no existe en otra región. Usa la misma región en la consola, en la política IAM y en `AWS_SES_REGION`. El valor previsto es `us-east-1`.

Límites del sandbox: 200 correos cada 24 horas, 1 correo por segundo, y solo direcciones verificadas.

## Qué dejar sin tocar

- No pulses **Request production access** ni completes el asistente si te pide un dominio. Sin dominio, AWS suele denegar la salida del sandbox.
- No actives Virtual Deliverability Manager, IPs dedicadas ni recepción de correo.
- No crees credenciales SMTP. Symfony habla con la API (`ses:SendEmail`).
- No actives el seguimiento de aperturas ni de clics. Reescribe los enlaces del correo.
- Deja activa la lista de supresión de la cuenta (rebotes y quejas). Es el valor por defecto.
- No generes claves de acceso con el usuario root ni reutilices las de S3.

## 1. Entrar en la región correcta

1. Inicia sesión en la consola de AWS con un usuario que pueda administrar SES e IAM.
2. Arriba a la derecha, elige **US East (N. Virginia) us-east-1**, salvo que uses otra región a propósito.
3. Abre **Amazon Simple Email Service**.
4. Si aparece un asistente **Get set up** que pide un dominio o acceso de producción, ciérralo.

## 2. Confirmar el sandbox

1. Abre **Account dashboard**.
2. Comprueba que la cuenta está en el **sandbox**.
3. Anota los límites: 200 mensajes / 24 h y 1 mensaje / segundo.
4. Si el envío está **paused** o la cuenta está en revisión, detente. Una cuenta nueva a veces no puede enviar hasta que AWS la revise.

## 3. Verificar el correo personal

1. **Configuration** → **Identities** → **Create identity**.
2. Identity type: **Email address**.
3. Escribe el buzón exacto que también recibirá las pruebas. Un alias con `+` no hereda la verificación de la dirección base.
4. No asignes configuration set, etiquetas ni MAIL FROM.
5. **Create identity**. El estado queda en **Verification pending**.
6. Abre el mensaje **Amazon Web Services – Email Address Verification Request**. Revisa spam. El enlace caduca a las 24 horas.
7. Confírmalo. En **Identities**, el estado debe pasar a **Verified**. Si el enlace expiró, reenvía la verificación desde la identidad.
8. Copia el ARN, con esta forma: `arn:aws:ses:us-east-1:123456789012:identity/tu-correo@gmail.com`.

Esa misma dirección será `MAILER_FROM_ADDRESS` y `MAILER_REDIRECT_TO`.

## 4. Crear la política IAM

IAM es global. La región escrita en el ARN sí importa.

1. **IAM** → **Policies** → **Create policy** → pestaña **JSON**.
2. Sustituye región, ID de cuenta y correo:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowSendFromVerifiedMail",
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "arn:aws:ses:us-east-1:123456789012:identity/tu-correo@gmail.com"
        }
    ]
}
```

3. Nombre: `PokedexSesSendEmail`.

`ses:SendEmail` es la acción de la API v2 (`ses+api`). `ses:SendRawEmail` cubre el envío MIME de la API v1. El recurso es solo esa identidad.

## 5. Crear el usuario de la aplicación

1. **IAM** → **Users** → **Create user**.
2. User name: `pokedex-ses-mailer`.
3. Sin acceso a la consola.
4. Adjunta solo `PokedexSesSendEmail`. No añadas `AmazonSESFullAccess` ni permisos de S3.

## 6. Crear la clave de acceso

1. Abre `pokedex-ses-mailer` → **Security credentials** → **Create access key**.
2. Caso de uso: **Application running outside AWS**.
3. Copia la access key y el secret en ese momento. El secret no se vuelve a mostrar.
4. Guárdalos solo en `.env.local`. No los commitees.

Si la clave se filtra: desactívala, crea otra, actualiza `.env.local` y borra la antigua.

## 7. Probar la identidad y el permiso

La prueba de la consola valida la identidad. La de AWS CLI valida el usuario IAM.

1. SES, región `us-east-1`, abre la identidad verificada.
2. **Send test email** hacia esa misma dirección.
3. El mensaje debe llegar a ese buzón. Esta prueba usa tu usuario de consola, no `pokedex-ses-mailer`.

Para el usuario IAM, en una terminal local y sin guardar las claves en el repositorio:

```bash
AWS_ACCESS_KEY_ID="AKIA..." \
AWS_SECRET_ACCESS_KEY="el-secreto" \
AWS_DEFAULT_REGION="us-east-1" \
aws sesv2 send-email \
  --from-email-address "tu-correo@gmail.com" \
  --destination "ToAddresses=tu-correo@gmail.com" \
  --content "Simple={Subject={Data=Prueba SES,Charset=UTF-8},Body={Text={Data=Sandbox correcto,Charset=UTF-8}}}"
```

Una respuesta con `MessageId` confirma región, identidad y política.

## 8. Variables de `.env.local`

El secret dentro de `MAILER_DSN` va codificado para URL (`+` pasa a `%2B`, `/` a `%2F`, `=` a `%3D`). Desde la raíz del proyecto, `read -rs` no muestra lo que escribes:

```bash
read -rs SECRET
docker compose exec -e SECRET php php -r 'echo rawurlencode((string) getenv("SECRET")), PHP_EOL;'
unset SECRET
```

```dotenv
AWS_SES_REGION=us-east-1
AWS_SES_ACCESS_KEY_ID=AKIA...
AWS_SES_SECRET_ACCESS_KEY=el-secreto-sin-codificar
MAILER_DSN=ses+api://AKIA...:SECRETO_URLENCODED@default?region=us-east-1
MAILER_FROM_ADDRESS=tu-correo@gmail.com
MAILER_FROM_NAME=Pokedex
MAILER_REDIRECT_TO=tu-correo@gmail.com
```

`AWS_SES_SECRET_ACCESS_KEY` se guarda sin codificar. `MAILER_FROM_ADDRESS` y `MAILER_REDIRECT_TO` deben ser exactamente la identidad verificada.

`DEFAULT_URI` tiene que ser la URL real de la aplicación, con esquema, para que el enlace del comando de backup sea absoluto.

El envío real lo hace el worker. Sin él, el mensaje queda en `messenger_messages`:

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

En `dev`, si `MAILER_REDIRECT_TO` está vacío, la aplicación no encola el correo.

## Errores frecuentes

- **Email address is not verified** o **MessageRejected**: el remitente o el destinatario no están en **Verified**, o la identidad está en otra región. En desarrollo, el destinatario que SES ve es `MAILER_REDIRECT_TO`.
- **AccessDeniedException**: el ARN de la política no coincide en región, cuenta o correo. Si el ARN es correcto y SESv2 sigue denegando, cambia el `Resource` a `"*"` y añade la condición `ses:FromAddress` con el correo verificado. No uses `AmazonSESFullAccess`.
- **Verification pending** permanente: el enlace caducó. Reenvía la verificación desde la identidad.
- **Daily message quota exceeded**: se agotaron los 200 mensajes del día.
- **Throttling** o **Maximum sending rate exceeded**: se superó 1 mensaje por segundo. Messenger reintenta.
- El mensaje no llega y SES no muestra error: revisa spam y **Suppression list**.

## Cuando exista el dominio

El código no cambia.

1. En la misma región, **Create identity** → **Domain**. Escribe el dominio apex y deja **Easy DKIM** activado.
2. Publica en el DNS los 3 CNAME de DKIM. La verificación puede tardar hasta 72 horas.
3. Configura un **Custom MAIL FROM** en un subdominio (`mail.ejemplo.com`). Publica el MX y el TXT de SPF que indica la consola.
4. Publica un TXT de DMARC en el dominio apex. Al principio: `v=DMARC1; p=none; rua=mailto:tu-correo@gmail.com`.
5. Edita `PokedexSesSendEmail` y añade el ARN `arn:aws:ses:us-east-1:ACCOUNT_ID:identity/ejemplo.com`.
6. Cuando el dominio y el MAIL FROM estén **Verified** y **Successful**, cambia `MAILER_FROM_ADDRESS` a `no-reply@ejemplo.com`.
7. En **Account dashboard**, solicita **production access**. Describe correos transaccionales a usuarios de la aplicación. No pidas producción para marketing.
8. Fuera de sandbox, quita `MAILER_REDIRECT_TO` en los entornos reales. En `dev` la redirección se mantiene.
