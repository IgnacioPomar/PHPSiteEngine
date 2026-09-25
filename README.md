# PHPSiteEngine

Una base mínima y reutilizable para construir y mantener **intranets modulares**. Se descarga como submódulo de tu proyecto.

## Qué es PHPSiteEngine

PHPSiteEngine no resuelve un problema de negocio concreto: resuelve las piezas que se repiten en *cualquier* intranet interna, para que cada plugin (cada uno resolviendo una necesidad de negocio distinta — por ejemplo, un plugin de gestión de vacaciones, otro de inventario, otro de partes de trabajo) no tenga que reimplementarlas desde cero. Estas piezas son:

- **Autenticación de usuarios**, con recuperación de contraseña y login de aplicaciones (clientes no-browser).
- **Permisos por usuario/grupo a nivel de nodo de menú**.
- Un **cargador de plugins** con un contrato simple: `getPlgInfo` / `checkParams` / `checkPerms`.
- **Scaffolding de formularios y esquema de BD** (`AutoForm` / `DbSchema` / `Migrator`) para que cada plugin monte su propio CRUD sin reescribir el acceso a datos desde cero.
- Un **sistema de menú** (JSON o BD) y de **skins** para la presentación.

La pieza de valor es esa: dar a cada plugin una base común de login/permisos/menú/CRUD ya resuelta, en vez de que cada uno reimplemente su propia autenticación y su propio acceso a BD.

## Qué NO es PHPSiteEngine

- **No es un CMS de contenido.** No gestiona páginas, Markdown, versionado ni publicación de contenido. Si un proyecto concreto necesita eso, se implementa como un plugin más sobre esta base, no como parte del núcleo.
- **No es un framework HTTP de propósito general.** No trae un router de rutas arbitrario ni pretende sustituir a Laravel/Symfony para aplicaciones web genéricas.
- **No trae un ORM completo.** `AutoForm`/`DbSchema` son scaffolding ligero para generar formularios y SQL básico a partir de una definición de tabla, no un mapeador de relaciones con query builder avanzado.

## Por qué existe

Para intranets internas con varios plugins heterogéneos por empresa, un framework de propósito general aporta más complejidad conceptual (rutas, middlewares, ORM completo, ecosistema de paquetes) de la que se necesita para algo de uso puramente interno.

PHPSiteEngine prioriza que el equipo entienda el 100% del núcleo (es pequeño, ~20-25 ficheros) y que cada plugin nuevo se pueda montar rápido reutilizando login/permisos/menú/CRUD ya resueltos, con un contrato de plugin mínimo y explícito.

## Project Configuration

You will need to add a `index.php`  in your project (with at least the following content):

    <?php
    require_once 'PHPSiteEngine/SiteLauncher.php';
    use PHPSiteEngine\SiteLauncher;
    
	SiteLauncher::main (__DIR__, 'cfg/siteCfg.php');

Its also recomended to have a  `SiteConfiguration.php`  (please, choose wisely the name for your project) in your project (with at least the following content):

    <?php
    require_once 'PHPSiteEngine/SiteAdmin.php';
    use PHPSiteEngine\SiteAdmin;
    
	SiteAdmin::main (__DIR__, 'cfg/siteCfg.php');


In both cases, these are the parameters

| Parameter  | Value |
| ------------- | ------------- |
| rootPath  | The base root for the files  |
| config file  | [Optional] The configuration file  |

### Config file Vars
| Var  | Value |
| ------------- | ------------- |
| $GLOBALS ['Version']  | Just to check if a reinstall is mandatory  |
| $GLOBALS ['authAllowRecover']  | [TRUE/FALSE] allows the user to recover the password with the stored email  |
| $GLOBALS ['authAllowAppLogins']  | [TRUE/FALSE] allows login through the API (non-browser clients)  |
| $GLOBALS ['authKeepLogged']  | [TRUE/FALSE] keeps the user logged in via a persistent cookie  |
| $GLOBALS ['smtpHost']  | [Only if authAllowRecover] SMTP server used to send the password-recovery email |
| $GLOBALS ['smtpUsername']  | [Only if authAllowRecover] SMTP username, also used as the "From" address |
| $GLOBALS ['smtpPass']  | [Only if authAllowRecover] SMTP password |
| $GLOBALS ['recoverySubject']  | [Only if authAllowRecover] Subject line of the password-recovery email |
| $GLOBALS ['menuType']  | [0/1] Use a fixed json menu, or use a database menu. Default/recommended: `1` (database), since it needs no extra file and the installer/admin UI manage it directly |
| $GLOBALS ['dbserver']  | Mariadb Server  |
| $GLOBALS ['dbport']  | database Port
| $GLOBALS ['dbuser']  | database user
| $GLOBALS ['dbpass']  | database password
| $GLOBALS ['dbname']  | database
| $GLOBALS ['plgs']  | Path, retaive to rootPath, with the plugins |
| $GLOBALS ['skin']  |  Path, retaive to rootPath, with the skin
| $GLOBALS ['jsonMenu']  | The json menu to use (only if menuType == 0). Defaults to `mainMenu.json`, looked up relative to the config folder (e.g. `cfg/mainMenu.json`) |

> Esta tabla debe mantenerse sincronizada con `Auth.php`/`Site.php`/`authMailer.php` — el desajuste anterior (`authRecover`/`authLog` documentados pero no leídos por el código) es justamente el tipo de deriva que se quiere evitar. Si añades o renombras una clave de `$GLOBALS` en el código, actualiza esta tabla en el mismo cambio.
