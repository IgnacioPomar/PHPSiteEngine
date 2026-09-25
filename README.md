# PHPSiteEngine
 A engine to generate and mantain modular intranets
Just download as a submodule of your project.

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
| $GLOBALS ['menuType']  | [0/1] Use a fixed json menu, or use a database menu. Default/recommended: `1` (database), since it needs no extra file and the installer/admin UI manage it directly |
| $GLOBALS ['dbserver']  | Mariadb Server  |
| $GLOBALS ['dbport']  | database Port
| $GLOBALS ['dbuser']  | database user
| $GLOBALS ['dbpass']  | database password
| $GLOBALS ['dbname']  | database
| $GLOBALS ['plgs']  | Path, retaive to rootPath, with the plugins |
| $GLOBALS ['skin']  |  Path, retaive to rootPath, with the skin
| $GLOBALS ['jsonMenu']  | The json menu to use (only if menuType == 0). Defaults to `mainMenu.json`, looked up relative to the config folder (e.g. `cfg/mainMenu.json`) |

 
 
 