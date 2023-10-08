<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\SimpleMenu;

use Fisharebest\Webtrees\Module\UserFavoritesModule;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Fisharebest\Localization\Translation;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusAction;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Anonymous class - provide a custom menu option
 */
return new class extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleGlobalInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleGlobalTrait;

    protected const ROUTE_URL   = '/tree/{tree}/favoritesMenu/{menu}';

     // Module constants
    public const CUSTOM_AUTHOR = 'Bwong';
    public const CUSTOM_VERSION = '1.0';
    public const GITHUB_REPO = 'none';
    public const AUTHOR_WEBSITE = ''; //'https://none.com';
    public const CUSTOM_SUPPORT_URL = ''; //self::AUTHOR_WEBSITE . '/none/';

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate('Favorites Menu');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Favorites Menu” module */
        return I18N::translate('Manage favorites based on current page.');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

    /**
     * Fetch the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersion(): string
    {
        return 'https://github.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/releases/latest';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        Registry::routeFactory()->routeMap()
            ->get(static::class, static::ROUTE_URL, $this);

        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

     /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

     /**
     * The default position for this menu.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultMenuOrder(): int
    {
        return 99;
    }

    /**
     * A menu, to be added to the main application menu.
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        $user = Auth::user();
        $user_id = $user->id();

        if (!is_int($user_id)) {
          return null;
        }

        $tree_name = $tree->name();
        $uri = $_SERVER['REQUEST_URI'];
        $path = explode('/',$uri);
        $tree_index = array_search('tree',$path);
        if ((FALSE === $tree_index) || (count($path) < (4 + $tree_index))) {
          return null;
        }
        $type = $path[$tree_index + 2];
        switch ($type) {
            case 'individual':
              $gedcom_type = 'INDI';
              break;
            case 'family':
              $gedcom_type = 'FAM';
              break;
            case 'media':
              $gedcom_type = 'OBJE';
              break;
            default:
              return null;
        }
        $xref = $path[$tree_index + 3];

        $gedcom = DB::table('gedcom')
            ->where('gedcom_name', '=', $tree_name)
            ->get()
            ->toArray();

        if (!$gedcom) {
          // Cannot find gedcom family record.
          return null;
        }

        // Get current favorites setting. 
        $result = DB::table('favorite')
            ->where('gedcom_id', '=', $gedcom[0]->gedcom_id)
            ->where('user_id', '=', $user_id)
            ->where('favorite_type', '=', $gedcom_type)
            ->where('xref', '=', $xref)
            ->get()
            ->toArray();

        // Check if favorite status change requested.
        $parameters = explode('&',$_SERVER['QUERY_STRING']);
        $args = [];
        foreach ($parameters as $i => $value) {
          switch ($value) {
            case 'favorites-menu-true':
              if (!$result) {
                // Add to favorites.
                DB::table('favorite')->insert([
                  'gedcom_id' => $gedcom[0]->gedcom_id,
                  'user_id' => $user_id,
                  'favorite_type' => $gedcom_type,
                  'xref' => $xref]);
                $result = TRUE;
              }
              break;
            case 'favorites-menu-false':
              if ($result) {
                // Remove from favorites.
                DB::table('favorite')
                  ->where('gedcom_id', '=', $gedcom[0]->gedcom_id)
                  ->where('user_id', '=', $user_id)
                  ->where('favorite_type', '=', $gedcom_type)
                  ->where('xref', '=', $xref)
                  ->delete();
                $result = FALSE;
              }
              break;
            case '':
              // Clean up any empty parameters.
              unset($parameters[$i]);
              break;
            default:
              $args[] = $value;
              break;
          }
        }

        // Setup display and url parameter. 
        if ($result) {
            $class = 'favorites-menu-true';
            $prefix = '[*] ';
            $args[] = 'favorites-menu-false';
            $action = 'Remove from favorites';
        } else {
            $class = 'favorites-menu-false';
            $prefix = '[ ] ';
            $args[] = 'favorites-menu-true';
            $action = 'Make favorite';
        }


        // Generate parameters.
        if ($args) {
          $args = '?' . implode('&',$args);
        } else {
          $args = '';
        }

        // Set up submenu.
        $my_url = explode('?',$uri)[0];
        $submenu[] = new Menu('-- '.I18N::translate($action).' --', e("$my_url$args"), 'favorites-menu-action');

        // Get current favorites setting.
        $favorites = (new UserFavoritesModule())->getFavorites($tree,$user);

        $path_prefix = array_slice($path,0,$tree_index);
        if ($path_prefix) {
          $url_prefix = implode('/',array_slice($path,0,$tree_index));
        } else {
          $url_prefix = '';
        }

        foreach ($favorites as $favorite) {
          $type = $favorite->favorite_type;
          $name = $type . ': ' . $favorite->record->fullName();
          $id = $favorite->xref;
          switch ($type) {
            case 'INDI':
              $url = "individual/$id";
              break;
            case 'FAM':
              $url = "family/$id";
              break;
            case 'OBJE':
              $url = "media/$id";
              break;
          }
          $submenu[] = new Menu($name, e( "$url_prefix/tree/$tree_name/". $url), "favorites-menu-$type favorites-menu-item");
        }

        // Strip old arguments.
        $uri = explode('?',$uri)[0];
        return new Menu($prefix . I18N::translate('Favorite'), '#', $class, ['rel' => 'nofollow'], $submenu);
    }

    /**
     * Get the url slug for this page
     */
    public function getSlug($string): String
    {
        return preg_replace('/\s+/', '-', strtolower(preg_replace("/&([a-z])[a-z]+;/i", "$1", htmlentities($string))));
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        return '<link rel="stylesheet" href="' . e($this->assetUrl('css/style.css')) . '">';
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
      return null; // Should never be called. 
    }
};
