# Sitios en la EC2 compartida

Esta guía deja `pokedex.tutoyotaseminuevo.com` como primer sitio de la EC2 `apps`, en la cuenta `444040489723` (alias de consola `tutoyotaseminuevo`). La región es `us-east-1`. La misma máquina aloja los sitios públicos y, en subdominios concretos, las copias de desarrollo. Cada aplicación tiene su base, su `.env` y su worker. Todas entran por la misma IP elástica.

La consola se usa con el usuario `admin-cesar-toyota`. Conviene tener MFA activo en ese usuario. Ese usuario no se copia dentro de los contenedores.

Los archivos de despliegue del repositorio son:

| Archivo | Uso |
| --- | --- |
| [compose.prod.yaml](../../compose.prod.yaml) | PHP, worker de Messenger y PostgreSQL de un cliente |
| [deploy/edge-compose.yaml](../../deploy/edge-compose.yaml) | Caddy y Gotenberg, compartidos |
| [deploy/Caddyfile](../../deploy/Caddyfile) | Plantilla del primer sitio. En el servidor se copia a `/srv/edge/Caddyfile` |
| [deploy/deploy.sh](../../deploy/deploy.sh) | Construye la imagen una vez, migra y recrea PHP y el worker |
| [deploy/client.env.example](../../deploy/client.env.example) | Plantilla del `.env` de un cliente |

`deploy.sh` no ejecuta `docker compose down -v`. Ese flag borra los datos de PostgreSQL.

## 1. Bucket, correo y usuario IAM

Entra en la consola con el alias `tutoyotaseminuevo` y fija la región **US East (N. Virginia)**.

1. Crea el bucket `pokedex-444040489724`. Deja activo el bloqueo de acceso público.
2. En Amazon SES, verifica el correo que va a aparecer como remitente. La cuenta nueva está en sandbox: solo entrega a identidades verificadas, con un tope de 200 mensajes cada 24 horas y 1 mensaje por segundo. Verificar el dominio `tutoyotaseminuevo.com` y salir del sandbox queda para cuando haya que escribir a cualquier destinatario. El código de correo no cambia.

   - Arriba a la derecha de la consola, el nombre de la región tiene que decir **US East (N. Virginia) us-east-1**. Si dice otra cosa, ábrelo y elige esa. Una identidad creada en otra región no existe en `us-east-1`.
   - En el buscador de arriba escribe `SES` y abre **Amazon Simple Email Service**.
   - Si aparece un asistente **Get set up** pidiendo un dominio o acceso de producción, ciérralo. No pulses **Request production access**.
   - En el menú izquierdo abre **Account dashboard**. El estado de la cuenta tiene que ser **Sandbox**. Si el envío está **Paused** o la cuenta está en revisión, detente: una cuenta nueva a veces no puede enviar hasta que AWS la revise.
   - En el menú izquierdo abre **Configuration** y luego **Identities**. Pulsa **Create identity**.
   - En **Identity type** elige **Email address**. En **Email address** escribe el buzón exacto, el mismo que recibirá las pruebas. Un alias con `+` no hereda la verificación de la dirección base.
   - No rellenes configuration set, etiquetas ni MAIL FROM. No actives seguimiento de aperturas ni de clics.
   - Pulsa **Create identity**. La fila queda en **Verification pending**.
   - Abre el mensaje **Amazon Web Services – Email Address Verification Request** en ese buzón. Revisa spam. El enlace caduca a las 24 horas. Si caducó, en la identidad pulsa **Resend** y usa el enlace nuevo.
   - Vuelve a **Identities**. El estado tiene que pasar a **Verified**.
   - Abre esa identidad y copia el ARN. Tiene esta forma: `arn:aws:ses:us-east-1:444040489723:identity/tu-correo@ejemplo.com`. Ese valor sustituye a `IDENTIDAD_VERIFICADA` en la política del paso siguiente, y el mismo correo es `MAILER_FROM_ADDRESS`. Mientras la cuenta siga en sandbox, el destinatario de cada prueba también tiene que estar verificado.
3. Crea el usuario IAM `pokedex`. Sin acceso a la consola y sin `AdministratorAccess`. Adjunta una política de cliente llamada `PokedexApp` y sustituye la identidad por la que acabas de verificar.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListPokedexPrefix",
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": "arn:aws:s3:::pokedex-444040489724",
      "Condition": {
        "StringLike": { "s3:prefix": ["pokedex/*"] }
      }
    },
    {
      "Sid": "ReadWritePokedexObjects",
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::pokedex-444040489724/pokedex/*"
    },
    {
      "Sid": "SendMailFromVerifiedIdentity",
      "Effect": "Allow",
      "Action": ["ses:SendEmail", "ses:SendRawEmail"],
      "Resource": "arn:aws:ses:us-east-1:444040489723:identity/IDENTIDAD_VERIFICADA"
    }
  ]
}
```

4. Crea una access key para `pokedex`. Guárdala en un gestor de contraseñas. La misma key rellena las variables de S3 y las de SES. No la pongas en git.

   - En el buscador de arriba escribe `IAM` y abre **IAM**. IAM no depende de la región de la esquina: da igual lo que diga arriba a la derecha.
   - En el menú izquierdo abre **Users**. Pulsa el nombre `pokedex`. Si no está en la lista, vuelve al paso 3 y créalo antes de seguir.
   - Abre la pestaña **Security credentials**.
   - Baja hasta **Access keys** y pulsa **Create access key**.
   - En el caso de uso elige **Application running outside AWS**. AWS recomienda un rol de instancia; para este entorno seguimos con clave. Marca la casilla de que entiendes el aviso y quieres continuar.
   - En la descripción opcional escribe `pokedex`. Pulsa **Create access key**.
   - En la pantalla siguiente copia **Access key** y **Secret access key**. El secreto se muestra una sola vez. Si cierras la página sin copiarlo, no se puede recuperar: crea otra clave y borra esta.
   - Guarda los dos valores en el gestor de contraseñas. Puedes pulsar **Download .csv file**, pero no dejes ese archivo en el repositorio ni en una carpeta sincronizada. Pulsa **Done**.
   - En el `.env` del servidor, `AWS_ACCESS_KEY_ID` y `AWS_SES_ACCESS_KEY_ID` reciben el Access key. `AWS_SECRET_ACCESS_KEY` y `AWS_SES_SECRET_ACCESS_KEY` reciben el secreto tal cual, sin codificar. En `MAILER_DSN` ese mismo secreto sí va codificado para una URL.

La instancia, el grupo de seguridad y la IP elástica los crea `admin-cesar-toyota` con el nombre `apps`. El usuario `pokedex` no tiene permisos de EC2: sus claves viven dentro del contenedor.

## 2. Instancia

Los pasos 1, 2 y 3 son la misma pantalla **Launch instance**. No pulses **Launch instance** hasta terminar el grupo de seguridad. La IP elástica se asocia después, cuando la máquina ya está en marcha.

1. Lanza una EC2 **Ubuntu Server 24.04 LTS (64-bit Arm)**, tipo `t4g.large`, disco gp3 de 100 GB, en una subred pública.

   - Arriba a la derecha, la región tiene que decir **US East (N. Virginia) us-east-1**.
   - En el buscador de arriba escribe `EC2` y abre **EC2**.
   - Pulsa el botón naranja **Launch instance**.
   - En **Name** escribe `apps`.
   - En **Application and OS Images** deja **Quick Start** y elige **Ubuntu**.
   - En la lista de AMIs elige **Ubuntu Server 24.04 LTS**. En **Architecture** cambia **64-bit (x86)** por **64-bit (Arm)**. Si dejas x86, el tipo `t4g.large` no aparece.
   - En **Instance type** abre la lista, busca `t4g.large` y selecciónalo. Son 2 vCPU y 8 GB de memoria.
   - Baja a **Configure storage**. El tamaño tiene que ser **100** GiB y el tipo **gp3**. Si el disco aparece cifrado, déjalo así. Con unas seis aplicaciones, 60 GB se queda corto entre imágenes, caché de Docker y bases. Esos 40 GB de más cuestan unos 3 USD al mes.
   - Sigue en esta misma pantalla con el par de claves y la red. El botón **Launch instance** queda para el final del paso 3.

2. Crea un par de claves nuevo para el primer acceso.

   - En la misma pantalla, en **Key pair (login)**, pulsa **Create new key pair**.
   - **Key pair name:** `apps`.
   - **Key pair type:** **ED25519**.
   - **Private key file format:** **.pem**.
   - Pulsa **Create key pair**. El navegador descarga `apps.pem`. Esa descarga ocurre una sola vez.
   - Mueve el archivo a un sitio privado de tu máquina. En la terminal de tu equipo: `chmod 400 apps.pem`. No lo subas a git ni a una carpeta sincronizada.
   - Esta clave es la del primer acceso como usuario `ubuntu`. La clave del despliegue automático se crea más adelante y es otra.

3. Grupo de seguridad: el puerto 22 solo desde tu IP; 80 y 443 abiertos. 5432 y 3306 cerrados.

   - En la misma pantalla, en **Network settings**, pulsa **Edit**.
   - **VPC:** la VPC por defecto. **Subnet:** **No preference** vale si la VPC es la de defecto; sus subredes son públicas.
   - **Auto-assign public IP:** **Enable**. Sin eso la instancia no sale a internet.
   - En **Firewall** elige **Create security group**.
   - **Security group name:** `apps`. En la descripción puedes escribir `SSH propio, HTTP y HTTPS publicos`.
   - La primera regla ya es **SSH**, puerto **22**. En **Source** elige **My IP**. La consola rellena tu dirección. Desde otra red tendrás que editar esta regla para volver a entrar.
   - Pulsa **Add security group rule**. **Type:** **HTTP**, puerto **80**. **Source:** **Anywhere-IPv4** (`0.0.0.0/0`).
   - Otra regla. **Type:** **HTTPS**, puerto **443**. **Source:** **Anywhere-IPv4** (`0.0.0.0/0`).
   - No agregues reglas para **5432** ni **3306**. El asistente no las abre si tú no las añades.
   - Revisa el resumen de la derecha: AMI Ubuntu Arm, `t4g.large`, disco 100 GB gp3, clave `apps`, tres reglas (22, 80 y 443). Pulsa **Launch instance**.
   - Abre **View all instances**. Espera a que **Instance state** sea **Running** y **Status check** pase a **2/2 checks passed**. El primer arranque tarda un par de minutos.
   - En el menú izquierdo abre **Volumes**. Selecciona el disco de 100 GB adjunto a `apps` y ponle la etiqueta **Name** = `apps`.

4. Asocia una IP elástica y quédate con esa dirección.

   - En el menú izquierdo de EC2 abre **Network & Security** y luego **Elastic IPs**.
   - Pulsa **Allocate Elastic IP address**.
   - Deja **Public IPv4 address pool** en **Amazon's pool of IPv4 addresses** y la región en `us-east-1`. En las etiquetas, **Name** = `apps`. Pulsa **Allocate**.
   - Selecciona la dirección nueva. Arriba, **Actions** → **Associate Elastic IP address**.
   - **Resource type:** **Instance**. En **Instance** elige `apps`. Si pregunta por la IP privada, deja la que ya muestra.
   - Pulsa **Associate**.
   - Copia esa **Elastic IP**. Es la que va en el registro A de Namecheap y, más adelante, en el secret `EC2_HOST`. La IP pública que salía en la lista de instancias deja de servir: a partir de aquí se entra por la elástica.
   - Déjala asociada. Si la liberas, AWS entrega otra dirección y el DNS deja de apuntar a la máquina.

## 3. DNS en Namecheap

El subdominio `pokedex.tutoyotaseminuevo.com` tiene que resolver a la IP elástica de `apps`. Let's Encrypt llega directo al puerto 80 de esa máquina, así que el DNS se queda en Namecheap, sin un proxy delante.

- Entra en [namecheap.com](https://www.namecheap.com/) e inicia sesión.
- Arriba abre **Domain List**. En la fila de `tutoyotaseminuevo.com` pulsa **Manage**.
- Abre la pestaña **Nameservers**. Tiene que decir **Namecheap BasicDNS**. Si dice **Custom DNS**, los registros que agregues en el paso siguiente no se usan. Cámbialo a **Namecheap BasicDNS** y guarda. No lo pases a Cloudflare ni a otro proveedor.
- Abre la pestaña **Advanced DNS**. Baja a **Host Records**.
- Si ya hay una fila con **Host** `pokedex` (un A, un CNAME o un **URL Redirect**), bórrala con el icono de la papelera. Una redirección manda el subdominio a otra web y el certificado no se emite.
- Pulsa **Add New Record**.
- **Type:** **A Record**.
- **Host:** `pokedex`. Solo esa palabra. Namecheap añade solo `.tutoyotaseminuevo.com`. No escribas `pokedex.tutoyotaseminuevo.com`.
- **Value:** la IP elástica, solo los números, por ejemplo `54.123.45.67`. Sin `https://` y sin el nombre del dominio.
- **TTL:** **Automatic**.
- Pulsa el visto verde de esa fila. Si arriba aparece **Save All Changes**, púlsalo también. La tabla tiene que mostrar una fila **A Record**, host `pokedex`, valor la IP elástica.
- En tu máquina, espera un par de minutos y comprueba:

```bash
dig +short pokedex.tutoyotaseminuevo.com
```

- La respuesta tiene que ser la IP elástica. Si sale vacía, espera y repite: con TTL automático puede tardar hasta media hora. No sigas con Caddy hasta que ese comando devuelva la IP.

El certificado aparece cuando ese registro ya resuelve y Caddy está en marcha. Caddy lo renueva solo.

## 4. Preparar el servidor

Hay dos sitios y el prompt dice en cuál estás. En tu Mac se parece a `tu-usuario@MacBook ~ %`. Dentro del servidor se parece a `ubuntu@ip-172-31-...:~$`. Los comandos de instalación van en el servidor. La clave nueva se crea en el Mac.

### Entrar por SSH

- En el Mac abre **Terminal** (Spotlight, escribe `Terminal`). La sesión del navegador de la consola de AWS no hace falta.
- Ve a la carpeta donde descargaste `apps.pem`. Si sigue en Descargas:

```bash
cd ~/Downloads
ls apps.pem
chmod 400 apps.pem
```

- `chmod 400` deja la clave legible solo por ti. SSH rechaza el archivo si otras personas pueden leerlo.
- Sustituye la IP elástica del paso 2 y entra. El usuario del primer acceso es `ubuntu`.

```bash
ssh -i apps.pem -o IdentitiesOnly=yes ubuntu@LA_IP_ELASTICA
```

- La primera vez pregunta `Are you sure you want to continue connecting (yes/no/[fingerprint])?`. Escribe `yes` y pulsa Enter. Esa pregunta sale una sola vez por máquina.
- Si dice `UNPROTECTED PRIVATE KEY FILE`, repite `chmod 400 apps.pem` en el Mac y vuelve a lanzar el `ssh`.
- Si dice `Permission denied (publickey)`, el `-i` no apunta a `apps.pem` o el usuario no es `ubuntu`.
- Si se queda esperando y acaba en `Operation timed out`, la dirección no es la IP elástica, o tu red cambió y la regla SSH **My IP** del grupo `apps` ya no coincide con la tuya. En EC2 edita esa regla del puerto 22 y pon la IP actual.
- Estás dentro cuando el prompt pasa a `ubuntu@ip-...:~$`. Para pegar en Terminal usa Cmd+V. Para salir, más adelante, escribe `exit`. Si se corta la sesión, repite el `ssh`: lo ya instalado sigue en el servidor.

### Instalar Docker, el swap y el usuario deploy

Estos comandos se pegan en la sesión cuyo prompt empieza por `ubuntu@`. `sudo` en esta imagen no pide contraseña. Si aparece un recuadro rosa de reinicio de servicios, Tab hasta **Ok** y Enter.

- Actualiza los paquetes e instala lo que hace falta para bajar Docker y clonar el repositorio:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl git
```

- Añade el repositorio oficial de Docker. La línea del `echo` es una sola línea, aunque se vea partida:

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

- Comprueba el cliente y el servicio. El primero imprime una versión. El segundo imprime una tabla, aunque esté vacía:

```bash
docker compose version
sudo docker ps
```

- Crea 4 GB de swap. Pega este bloque una sola vez. Si `swapon --show` ya lista `/swapfile`, sáltalo: repetir el `echo` duplica la línea en `/etc/fstab`.

```bash
sudo fallocate -l 4G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
swapon --show
```

- `swapon --show` tiene que mostrar `/swapfile` con tamaño 4G.
- Crea el usuario `deploy`, mételo en el grupo `docker` y prepara las carpetas de la aplicación y del proxy. `deploy` entra solo con clave. El grupo `docker` vale en el próximo inicio de sesión de ese usuario.

```bash
sudo useradd --create-home --shell /bin/bash deploy
sudo usermod -aG docker deploy
sudo mkdir -p /srv/pokedex/clients/pokedex/data/postgresql /srv/edge
sudo chown -R deploy:deploy /srv/pokedex /srv/edge
```

- Si `useradd` dice que el usuario ya existe, sigue con `usermod` y `mkdir`.
- `ls -la /srv` tiene que mostrar `pokedex` y `edge` con dueño `deploy`.

### Clave solo para el despliegue

`apps.pem` sirve para entrar como `ubuntu`. GitHub Actions usa otra clave, y esa solo abre la puerta de `deploy`. La privada se queda en tu Mac. Al servidor va la pública.

- En el Mac abre otra pestaña de Terminal con Cmd+T. El prompt de esta pestaña es el de tu Mac. La pestaña anterior sigue dentro del servidor.
- Crea la clave en `~/.ssh`. `-N ""` la deja sin passphrase: Actions no puede escribir una passphrase. El aviso de passphrase vacía es el resultado esperado. Si dice que el archivo ya existe, no lo sobrescribas.

```bash
mkdir -p ~/.ssh
ssh-keygen -t ed25519 -f ~/.ssh/pokedex-deploy -C deploy@pokedex -N ""
chmod 400 ~/.ssh/pokedex-deploy
```

- Quedan dos archivos. `~/.ssh/pokedex-deploy` empieza por `-----BEGIN OPENSSH PRIVATE KEY-----`: es el secret `EC2_SSH_KEY` y no se sube a git. `~/.ssh/pokedex-deploy.pub` es una línea que empieza por `ssh-ed25519`: esa va al servidor.
- Desde el Mac, copia la pública a `deploy`. Sustituye la IP. El `<` lee el archivo en tu Mac y el `ssh` lo escribe en el servidor:

```bash
ssh -i ~/Downloads/apps.pem -o IdentitiesOnly=yes ubuntu@LA_IP_ELASTICA 'sudo mkdir -p /home/deploy/.ssh && sudo tee /home/deploy/.ssh/authorized_keys > /dev/null && sudo chown -R deploy:deploy /home/deploy/.ssh && sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys' < ~/.ssh/pokedex-deploy.pub
```

ejemplo para pokedex en mac:
```
ssh -i "./AWS Server Toyota/apps.pem" -o IdentitiesOnly=yes ubuntu@IP_ELASTICA 'sudo mkdir -p /home/deploy/.ssh && sudo tee /home/deploy/.ssh/authorized_keys > /dev/null && sudo chown -R deploy:deploy /home/deploy/.ssh && sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys' < ~/.ssh/pokedex-deploy.pub
```

- Si `apps.pem` no está en `~/Downloads`, cambia esa ruta. El comando no imprime la clave. Una vuelta sin texto de error es el resultado esperado.
- Prueba el usuario nuevo, también desde el Mac:

```bash
ssh -i ~/.ssh/pokedex-deploy -o IdentitiesOnly=yes deploy@LA_IP_ELASTICA
```

- El prompt tiene que pasar a `deploy@ip-...:~$`. Escribe `docker ps`. Tiene que mostrar una tabla vacía, sin `permission denied`. Escribe `exit` para volver al Mac.
- Si responde `Permission denied (publickey)`, vuelve a la sesión `ubuntu` y revisa dueño y permisos. Tienen que ser `deploy` y `700` en la carpeta, `600` en el archivo, y el archivo tiene que ser la línea `ssh-ed25519`, no la clave privada (solo ejecutar si hay Permission denied):

```bash
sudo ls -ld /home/deploy/.ssh /home/deploy/.ssh/authorized_keys
sudo cat /home/deploy/.ssh/authorized_keys
```

### Clonar el repositorio y crear la red

- Vuelve a la pestaña cuyo prompt es `ubuntu@`. Si ya saliste, entra otra vez con `apps.pem`.
- Clona el repositorio público y crea la red que van a compartir Caddy y las aplicaciones:

```bash
sudo -u deploy -H git clone https://github.com/cesar-giraldo/pokedex-web-app.git /srv/pokedex/src
sudo -u deploy -H docker network create edge
```

- El clone tarda un momento y termina en `origin`. `sudo -u deploy -H git -C /srv/pokedex/src status` tiene que decir que estás en la rama `main`.
- `sudo -u deploy -H docker network ls` tiene que incluir `edge`. Si `docker network create` dice que `edge` ya existe, la red ya está creada y puedes seguir.
- Si `git clone` dice que `/srv/pokedex/src` ya existe y no está vacío, detente y revisa esa carpeta antes de borrar nada.

## 5. Entorno del primer cliente

El `.env` lo edita el usuario `deploy`. El archivo queda en modo `600`: lectura y escritura solo para su dueño. Con el prompt `deploy@` puedes abrirlo directo. Con el prompt `ubuntu@`, ese mismo archivo responde `Permission denied`. `sudo nano` lo guarda como `root` y entonces `deploy` también se queda fuera. Si la sesión abierta es la de `ubuntu`, déjala como está y abre otra para `deploy`.

- En el Mac, entra con la clave del paso 4. Sustituye la IP elástica:

```bash
ssh -i ~/.ssh/pokedex-deploy -o IdentitiesOnly=yes deploy@LA_IP_ELASTICA
```

- El prompt tiene que ser `deploy@ip-...:~$`. Copia la plantilla y ciérrala:

```bash
cp /srv/pokedex/src/deploy/client.env.example /srv/pokedex/clients/pokedex/.env
chmod 600 /srv/pokedex/clients/pokedex/.env
ls -l /srv/pokedex/clients/pokedex/.env
```

- `ls -l` tiene que mostrar `-rw-------` y `deploy deploy`. `-rw-------` es el modo `600`. Si el dueño sale `root`, sal de esta sesión, entra como `ubuntu` y corrige el dueño antes de seguir:

```bash
sudo chown deploy:deploy /srv/pokedex/clients/pokedex/.env
sudo chmod 600 /srv/pokedex/clients/pokedex/.env
```

- Genera el secreto de la aplicación. La terminal imprime una sola línea de 64 caracteres. Selecciónala y cópiala (Cmd+C). Esa línea es `APP_SECRET`.

```bash
openssl rand -hex 32
```

- Abre el archivo:

```bash
nano /srv/pokedex/clients/pokedex/.env
```

- Si dice `nano: command not found`, escribe `exit`, entra como `ubuntu` con `apps.pem` y ejecuta `sudo apt-get install -y nano`. Vuelve a entrar como `deploy` y abre el archivo otra vez.
- En nano el cursor se mueve con las flechas. Escribes en la línea, sin borrar el nombre de la variable. Ctrl+O y Enter guarda. Ctrl+X cierra. Cada valor va pegado al `=`, sin comillas y sin espacios. Una línea `APP_SECRET="abc"` deja las comillas dentro del secreto.
- En cualquier valor evita el carácter `$`. Compose lo toma como una variable y el valor llega vacío o partido.
- `APP_SECRET=` recibe la línea de `openssl`. El contenedor en `APP_ENV=prod` se niega a arrancar si este valor está vacío o es el secreto de desarrollo del repositorio.
- Inventa `DATABASE_PASSWORD` con letras y números. La misma contraseña va dentro de `DATABASE_URL`, entre `app:` y `@database`, sin espacios. La plantilla trae `postgresql://app:@database:5432/app_pokedex?serverVersion=18&charset=utf8` y el hueco es ese. Con la contraseña `SoloEjemplo12` la línea queda `postgresql://app:SoloEjemplo12@database:5432/app_pokedex?serverVersion=18&charset=utf8`. Inventa otra: esa es solo la forma. `@`, `:`, `/`, `#`, `?`, `&`, `%` o un espacio van tal cual en `DATABASE_PASSWORD` y codificados solo dentro de `DATABASE_URL`.
- `AWS_ACCESS_KEY_ID` y `AWS_SES_ACCESS_KEY_ID` reciben el Access key del usuario IAM `pokedex`, el mismo en las dos.
- `AWS_SECRET_ACCESS_KEY` y `AWS_SES_SECRET_ACCESS_KEY` reciben el secreto tal cual, el mismo en las dos.
- En `MAILER_DSN` ese secreto va codificado. Antes de guardarlo, en la sesión `deploy` ejecuta esto, pega el secreto cuando el cursor quede en blanco y pulsa Enter. La línea que imprime es la que va en el DSN (`+` pasa a `%2B`, `/` a `%2F`, `=` a `%3D`):

```bash
python3 -c 'import urllib.parse; print(urllib.parse.quote(input(), safe=""))'
```

- `MAILER_DSN` queda con esta forma, con tu Access key y el secreto ya codificado: `ses+api://ACCESS_KEY:SECRETO_CODIFICADO@default?region=us-east-1`. Sustituye `null://null`.
- `MAILER_FROM_ADDRESS` es el correo que quedó **Verified** en SES.
- `INITIAL_USER_EMAIL` es el correo del primer administrador del sitio. `INITIAL_USER_PASSWORD` es su contraseña de entrada: 8 caracteres o más, con letras y números. Es distinta de `DATABASE_PASSWORD`.
- Deja como están `COMPOSE_PROJECT_NAME`, `CLIENT_ALIAS`, `APP_ENV`, `APP_DEBUG`, `DEFAULT_URI`, `TRUSTED_HOSTS`, `TRUSTED_PROXIES`, los datos de base salvo la contraseña, `AWS_S3_BUCKET`, `AWS_S3_STORAGE_PREFIX`, `GOTENBERG_URL` y `CORS_ALLOW_ORIGIN`. `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` y `MAILER_REDIRECT_TO` pueden seguir vacíos.
- Ctrl+O, Enter y Ctrl+X. Vuelve a comprobar el permiso. Tiene que seguir en `-rw-------` y `deploy deploy`:

```bash
ls -l /srv/pokedex/clients/pokedex/.env
```

La plantilla ya apunta a `https://pokedex.tutoyotaseminuevo.com`, al bucket `pokedex-444040489724` y al prefijo `pokedex`. El Compose de esta aplicación ya limita la memoria: PHP 512 MB, el worker 256 MB y Postgres 512 MB. Gotenberg, compartido, queda en 1 GB.

## 6. Primer arranque

Entra como `deploy` (el grupo `docker` solo aplica en una sesión nueva) cuando el registro A ya resuelva a la IP elástica:

```bash
sudo -iu deploy
cd /srv/pokedex/src
git fetch origin main
git checkout --detach origin/main
cp deploy/Caddyfile /srv/edge/Caddyfile
docker compose -f deploy/edge-compose.yaml up -d
./deploy/deploy.sh
```

Esa copia de `/srv/edge/Caddyfile` es la que usa Caddy. Un despliegue de Pokedex no la sustituye. Los otros dominios se agregan ahí.

La primera construcción de la imagen tarda varios minutos. Al terminar:

```bash
docker compose --project-directory /srv/pokedex/clients/pokedex \
  --file /srv/pokedex/src/compose.prod.yaml \
  exec -T php php bin/console app:create-initial-data --no-interaction
```

Abre `https://pokedex.tutoyotaseminuevo.com`. Caddy guarda el certificado en su volumen y lo renueva solo. Si el navegador no confía en el sitio, espera a que el A propague y revisa `docker compose -f /srv/pokedex/src/deploy/edge-compose.yaml logs caddy`.

## 7. Correo y copias de la base

El worker de Messenger queda en marcha con el Compose del cliente. Sale cada hora y Docker lo vuelve a levantar. Sin ese proceso los correos se quedan en la tabla `messenger_messages`.

El backup diario sube un `.sql` a `pokedex/private/database-backups/` y avisa por correo, así que el worker tiene que estar arriba. El domingo se vacía el caché de construcción de Docker. Ese comando no toca volúmenes. Como `ubuntu` o con sudo:

```bash
sudo tee /etc/cron.d/apps <<'EOF'
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
15 3 * * * deploy docker compose --project-directory /srv/pokedex/clients/pokedex --file /srv/pokedex/src/compose.prod.yaml exec -T php php bin/console app:generate-database-backup
0 4 * * 0 deploy docker builder prune -f
EOF
sudo chmod 644 /etc/cron.d/apps
```

No ejecutes `docker compose down -v` ni `docker volume prune`. Los dos borran las bases. Cuando haya más aplicaciones, cada una suma su línea de backup en este archivo.

En AWS Backup, una regla semanal sobre el volumen de la instancia, con 7 copias. El backup de S3 cubre el contenido de la base. La snapshot cubre el disco si la instancia se pierde.

## 8. Despliegue automático

En el repositorio de GitHub, Settings → Secrets and variables → Actions:

| Secret | Valor |
| --- | --- |
| `EC2_HOST` | IP elástica |
| `EC2_USER` | `deploy` |
| `EC2_SSH_KEY` | clave privada de `pokedex-deploy`, completa, con las líneas `BEGIN` y `END` |

El workflow [deploy.yml](../../.github/workflows/deploy.yml) corre cuando CI termina bien tras un push a `main`. Entra por SSH, deja el checkout en ese commit y ejecuta `deploy/deploy.sh`. Un pull request no despliega. Mientras falte alguno de los tres secrets, el job avisa y termina en verde.

Cada ejecución construye `pokedex-app:<sha>`, migra, calienta la caché y recrea `php` y `messenger`. Para volver a una imagen anterior:

```bash
cd /srv/pokedex/src
APP_IMAGE_TAG=<sha-corto> docker compose \
  --project-directory /srv/pokedex/clients/pokedex \
  --file compose.prod.yaml \
  up -d --force-recreate --no-deps php messenger
```

`<sha-corto>` es el de `docker image ls pokedex-app`. No borres el volumen `data/postgresql`.

## 9. Otro sitio en la misma instancia

Otra aplicación (el sitio de montacargas, Royal Motors) no reutiliza el usuario `pokedex` ni el bucket. Lleva su directorio (`/srv/tutoyota`, `/srv/royalmotors`), su imagen, su Postgres, su usuario IAM y su bucket. El grupo de seguridad, la IP elástica y la red `edge` son los de `apps`.

1. En el DNS del dominio, un registro A hacia la misma IP elástica. Un subdominio de desarrollo, cuando exista, es otro A hacia esa IP, por ejemplo `dev.pokedex`.
2. Agrega el host en `/srv/edge/Caddyfile`, con el alias Docker de esa aplicación. Recarga el proxy:

```bash
docker compose -f /srv/pokedex/src/deploy/edge-compose.yaml up -d
```

3. Añade su línea de backup en `/etc/cron.d/apps`.

El subdominio de desarrollo de Pokedex, el día que exista, es otro Compose bajo `/srv/pokedex/clients/`, con otra base y el prefijo `pokedex-dev`. Su usuario IAM es `pokedex-dev`, limitado a ese prefijo, para que su `.env` no lea los archivos del sitio público. Los dos corren con `APP_ENV=prod`. Hasta ese día solo existe el usuario `pokedex`.

`deploy/deploy.sh` de este repositorio solo recorre `/srv/pokedex/clients`. No despliega las otras aplicaciones.

## Qué queda fuera

- Verificar el dominio en SES y pedir la salida del sandbox.
- Sustituir las access keys por un rol de instancia. Hoy el cliente S3 lee `AWS_ACCESS_KEY_ID`.
- Alta disponibilidad, balanceador o RDS. Pasar la base a RDS es cambiar `DATABASE_HOST` en el `.env` del cliente.
