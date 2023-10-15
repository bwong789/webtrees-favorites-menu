<?php

declare(strict_types=1);

namespace favorites_menu\Webtrees\Module\FavoritesMenu;

use Fig\Http\Message\StatusCodeInterface;

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
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Services\TreeService;
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
use Fisharebest\Webtrees\Module\UserFavoritesModule;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusAction;
use Illuminate\Database\Capsule\Manager as DB;
use Fig\Http\Message\RequestMethodInterface;

/**
 * Anonymous class - provide a custom menu option
 */
return new class extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleGlobalInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleGlobalTrait;

    protected const ROUTE_URL = '/tree/{tree}/favorites-menu';

    // Module constants
    public const CUSTOM_AUTHOR = 'Bwong789';
    public const CUSTOM_VERSION = '1.6';
    public const GITHUB_REPO = 'webtrees-favorites-menu';

    public const AUTHOR_WEBSITE = 'https://github.com/bwong789';
    public const CUSTOM_SUPPORT_URL = 'https://github.com/bwong789/webtrees-favorites-menu/issues';

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
            ->get(static::class, static::ROUTE_URL, $this)
            ->allows(RequestMethodInterface::METHOD_POST);

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
     * Get all favorites.
     *
     * @param integer $user_id
     *
     * @return array
     *   Array of arrays with keys: group, title, type, url.
     */
    public function getFavorites($user_id):array {
      $uri = $_SERVER['REQUEST_URI'];
      $path = explode('/',$uri);
      $tree_index = array_search('tree',$path);

      $path_prefix = array_slice($path,0,$tree_index);
      if ($path_prefix) {
        $url_prefix = implode('/',array_slice($path,0,$tree_index));
      } else {
        $url_prefix = '';
      }

      // Get favorites.
      $result = [];
      $tree_service = app(TreeService::class);
      assert($tree_service instanceof TreeService);

      $favorites = DB::table('favorite')
            ->where('user_id', '=', $user_id)
            ->get()
            ->all();


      foreach ($favorites as $favorite) {
        $ftype = $favorite->favorite_type;

        if ($ftype == 'URL') {
          $result[$favorite->favorite_id] = [
            'favorite_id' => $favorite->favorite_id,
            'title' => $favorite->title,
            'type' => 'URL',
            'url' => $favorite->url,
            'group' => $favorite->note ? $favorite->note : '',
            'xref' => '',
            'gedcom_id' => '',
          ];
        } elseif ($favorite->xref) {
          $favorite_tree = $tree_service->find(intval($favorite->gedcom_id));
          $gedcom = Registry::gedcomRecordFactory()->make($favorite->xref, $favorite_tree);
          $result[$favorite->favorite_id] = [
            'favorite_id' => $favorite->favorite_id,
            'title' => $gedcom->fullName(),
            'type' => $ftype,
            'url' => $gedcom->url(),
            'group' => $favorite->note ? $favorite->note : '',
            'xref' => $favorite->xref,
            'gedcom_id' => $favorite->gedcom_id,
            ];
        } else {
          FlashMessages::addMessage(
              I18N::translate('Null xref for %s', $favorite->favorite_id));
        }
      }
      return $result;
    }

    /**
     * Get one favorite.
     *
     * @param integer $favorite_id
     *
     * @return object|null
     *   Favorite record data.
     */
    public function getFavorite($favorite_id) {
      $result = DB::table('favorite')
         ->where('favorite_id', '=', $favorite_id)
         ->get()
         ->toArray();
      return $result ? $result[0] : null;
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

      $settings = $this->getSettings($user_id);
      $default_group = $settings['default_group'];
      $tree_name = $tree->name();
      $tree_id = $tree->id();

      // Make sure tree exists.
      $uri = $_SERVER['REQUEST_URI'];
      $my_url = explode('?',$uri)[0];
      $path = explode('/',$my_url);
      $tree_index = array_search('tree',$path);
      if (FALSE === $tree_index) {
        return null;
      }

      if (count($path) >= (4 + $tree_index)) {
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
          case 'source':
            $gedcom_type = 'SOUR';
            break;
          case 'repository':
            $gedcom_type = 'REPO';
            break;
          default:
            $gedcom_type = 'URL';
            break;
        }
        $xref = $path[$tree_index + 3];
      } else {
        $gedcom_type = 'URL';
      }

      // Get current favorites setting. 
      $parameters = explode('&',htmlspecialchars_decode($_SERVER['QUERY_STRING']));
      $args = [];

      if ($gedcom_type == 'URL') {
        // Generate URI.
        $my_parameters = [];
        foreach($parameters as $parameter) {
          switch ($parameter) {
            case 'favorites-menu-move':
            case 'favorites-menu-false':
            case 'favorites-menu-true':
            case '':
              break;
            default:
              $my_parameters[] = $parameter;
              break;
          }
        }
        
        $my_favorite_url = $my_url . ($my_parameters ? ('?' . implode('&',$my_parameters)) : '');

        $result = DB::table('favorite')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $user_id)
            ->where('favorite_type', '=', 'URL')
            ->where('url', '=', $my_favorite_url)
            ->get()
            ->toArray();

        $my_group = $result ? $result[0]->note : '';
        foreach ($parameters as $i => $value) {
          switch ($value) {
            case 'favorites-menu-move':
              // Change group
              DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('user_id', '=', $user_id)
                    ->where('favorite_type', '=', 'URL')
                    ->where('url', '=', $my_favorite_url)
                    ->update(['note' => ($default_group ? $default_group : null)]);
              FlashMessages::addMessage(
                    I18N::translate('Favorite moved to group: ') .
                    "[$default_group]");
              $my_group = $group;
              break;
            case 'favorites-menu-true':
              if (!$result) {
                // Add to favorites.
                DB::table('favorite')->insert([
                    'gedcom_id' => $tree->id(),
                    'user_id' => $user_id,
                    'favorite_type' => 'URL',
                    'url' => $my_favorite_url,
                    'title' => implode(' ',array_slice($path, $tree_index + 2)),
                    'note' => ($default_group ? $default_group : null)]);
                $result = TRUE;
              }
              break;
            case 'favorites-menu-false':
              if ($result) {
                // Remove from favorites.
                DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('user_id', '=', $user_id)
                    ->where('favorite_type', '=', 'URL')
                    ->where('url', '=', $my_favorite_url)
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
      } else {
        $result = DB::table('favorite')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $user_id)
            ->where('favorite_type', '=', $gedcom_type)
            ->where('xref', '=', $xref)
            ->get()
            ->toArray();

        $my_group = $result ? $result[0]->note : '';
        foreach ($parameters as $i => $value) {
          switch ($value) {
            case 'favorites-menu-move':
              // Change group
              DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('user_id', '=', $user_id)
                    ->where('favorite_type', '=', $gedcom_type)
                    ->where('xref', '=', $xref)
                    ->update(['note' => ($default_group ? $default_group : null)]);
              FlashMessages::addMessage(
                    I18N::translate('Favorite moved to group: ') .
                    "[$default_group]");
              $my_group = $group;
              break;
            case 'favorites-menu-true':
              if (!$result) {
                // Add to favorites.
                DB::table('favorite')->insert([
                    'gedcom_id' => $tree->id(),
                    'user_id' => $user_id,
                    'favorite_type' => $gedcom_type,
                    'xref' => $xref,
                    'note' => ($default_group ? $default_group : null)]);
                $result = TRUE;
              }
              break;
            case 'favorites-menu-false':
              if ($result) {
                // Remove from favorites.
                DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
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
      }

      // Setup display and url parameter. 
      if ($result) {
          if ($default_group != $my_group) {
            $move_args = $args;
            $move_args[] = 'favorites-menu-move';
            $move_args = '?' . implode('&',$move_args);
          }
          $class = 'favorites-menu-true';
          $prefix = '&#9745; '; // '[*] ';
          $args[] = 'favorites-menu-false';
          $action = I18N::translate('Remove favorite');
      } else {
          $class = 'favorites-menu-false';
          $prefix = '&#9744; '; // '[ ] ';
          $args[] = 'favorites-menu-true';
          $action = I18N::translate('Add favorite');
      }

      // Generate parameters.
      if ($args) {
        $args = '?' . implode('&',$args);
      } else {
        $args = '';
      }


      // Generate submenu.
      $path_prefix = array_slice($path,0,$tree_index);
      if ($path_prefix) {
        $url_prefix = implode('/',array_slice($path,0,$tree_index));
      } else {
        $url_prefix = '';
      }

      $submenu[] = new Menu(
         htmlspecialchars($default_group),
         e( "$url_prefix/tree/$tree_name/favorites-menu"),
         "favorites-menu-first-group favorites-menu-group favorites-menu-item");

      if ($action) {
        $submenu[] = new Menu(
          I18N::translate($action),
          e("$my_url$args"),
          $class . '-item favorites-menu-action favorites-menu-item');
        if (isset($move_args)) {
          $submenu[] = new Menu(
            I18N::translate('Move from')." [$my_group]",
            e("$my_url$move_args"),
            'favorites-menu-move-item favorites-menu-action favorites-menu-item');
        }
      }

      // Add primary menu.
      $this->getSubmenu($default_group, $submenu, $user_id, $tree_id);

      // Add secondary menus.
      $user_service = app(UserService::class);
      assert($user_service instanceof UserService);
      foreach($settings['secondary'] as $value) {
        // Do not add if no secondary menu selected.
        if (',' == $value) {
          break;
        }
        [$group_user_id, $group] = explode(',', $value, 2);
        if ($group_user_id) {
          $favorites = [];
          $groups = [];
          $hash = [];
          $this->generateGroups($group_user_id, $favorites, $groups, $hash);
          $group_user = $user_service->find(intval($group_user_id));

          if (isset($groups[$group])) {
            $submenu[] = new Menu(
               htmlspecialchars($group .
                 ($group_user_id != $user_id ?
                  ' @ ' . $group_user->realName() :
                  '')),
               e( "$url_prefix/tree/$tree_name/favorites-menu"),
               "favorites-menu-group favorites-menu-item");
            $this->getSubmenu($group, $submenu, $group_user_id, $tree_id);
          }
        }
      }

      // Return menu item.
      return new Menu($prefix . I18N::translate('Favorite'), '#', $class, ['rel' => 'nofollow'], $submenu);
    }


    /**
     * Get submenu.
     *
     * @param array   &$submenu
     *
     * @param string  $group
     *
     * @param integer $user_id
     *
     * @param integer $tree_id
     *   Used to check for items in another tree.
     */
    private function getSubmenu($group, &$submenu, $user_id, $tree_id) {
      $favorites = $this->getFavorites($user_id);
      foreach(['INDI', 'FAM', 'OBJE', 'URL', 'NOTE', 'SOUR', 'REPO' ] as $type) {
        foreach($favorites as $favorite) {
          if (($type == $favorite['type']) && ($group == $favorite['group'])) {
            $submenu[] = new Menu(
              $favorite['title'],
              e($favorite['url']),
              "favorites-menu-$type favorites-menu-item" .
                ($favorite['gedcom_id'] && ($favorite['gedcom_id'] != $tree_id) ?
                 ' favorites-menu-other-tree' :
                 ''));
          }
        }
      }
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
     * User settings.
     *
     * @var array
     *    Array is keyed by $user_id.
     */
    protected $settings = [];

    /**
     * Get settings.
     *
     * @param integer $user_id
     *
     * $return array
     */
    public function getSettings($user_id) {
      if (!isset($this->settings[$user_id])) {
        $result = DB::table('user_setting')
            ->where('setting_name', '=', 'favorites-menu-settings')
            ->where('user_id', '=', $user_id)
            ->get()
            ->toArray();
        $settings = [
          'default_group'      => '',    // Default group title string.
          'secondary'          => [],    // Lise of secondary menus ['user_id,group'].
        ];

        if ($result) {
          $settings = array_merge($settings, unserialize($result[0]->setting_value));
        } else {
          DB::table('user_setting')->insert([
             'user_id' => $user_id,
             'setting_name' => 'favorites-menu-settings',
             'setting_value' => serialize($settings)]);
        }

        $this->settings[$user_id] = $settings;
      }
      return $this->settings[$user_id];
    }

    /**
     * Set settings. Assumes getSettings called first.
     *
     * @param integer $user_id
     * @param array   $settings
     */
    public function setSettings($user_id, $settings) {
      $this->settings[$user_id] = $settings;

      DB::table('user_setting')
               ->where('setting_name', '=', 'favorites-menu-settings')
               ->where('user_id', '=', $user_id)
               ->update(['setting_value' => serialize($settings)]);
    }


    /**
     * Get setting
     *
     * @param integer $user_id
     * @param string  $name
     * @param string|array $default
     *
     * @return string|array
     */
    public function getSetting($user_id, $name, $default) {
      $settings = $this->getSettings($user_id);

      return isset($settings[$name]) ? $settings[$name] : $default;
    }

    /**
     * Set setting 
     *
     * @param integer $user_id
     * @param string  $name
     * @param string|array  $value
     */
    public function setSetting($user_id, $name, $value) {
      $settings = $this->getSettings($user_id);
      $settings[$name] = $value;
      $this->setSettings($user_id, $settings);
    }

    /**
     * Get shared group list.
     *
     * @param integer $user_id
     *
     * $return array
     */
    public function getShared($user_id) {
      $result = DB::table('user_setting')
            ->where('setting_name', '=', 'favorites-menu-shared')
            ->where('user_id', '=', $user_id)
            ->get()
            ->toArray();
      return $result ? unserialize($result[0]->setting_value) : [];
    }

    /**
     * Set shared group list.
     *
     * @param integer $user_id
     * @param array $shared
     *
     * $return array
     */
    public function setShared($user_id, $shared) {
      DB::table('user_setting')
            ->where('setting_name', '=', 'favorites-menu-shared')
            ->where('user_id', '=', $user_id)
            ->delete();
      if ($shared) {
        DB::table('user_setting')
            ->insert([
              'setting_value' => serialize($shared),
              'setting_name' => 'favorites-menu-shared',
              'user_id' => $user_id,
              ]);
      }
    }

    /**
     * Generate groups and hashs. 
     *
     * Parameter arrays are cleared and regenerated. 
     *
     * @param integer $user_id
     * @param array   &$favorites
     * @param array   &$groups
     * @param array   &$hash
     */
    protected function generateGroups($user_id, &$favorites, &$groups, &$hash) {
        $favorites = $this->getFavorites($user_id);

        $groups[''] = [
          'count' => 0,
          'favorites' => [],
          'md5' => hash('md5','')
        ];
        $hash[$groups['']['md5']] = '';

        foreach($favorites as $favorite_id => $favorite) {
          $group = $favorite['group'];
          if (isset($groups[$group])) {
            $groups[$group]['count'] += 1;
            $groups[$group]['favorites'][$favorite['type']][$favorite_id] = $favorite;
          } else {
            $groups[$group] = [
              $favorite['type'] => $favorite,
              'id' => $favorite_id,
              'count' => 1,
              'md5' => hash('md5',$group),
              'favorites' => [$favorite['type'] => [$favorite_id => $favorite]],
            ];
            $hash[$groups[$group]['md5']] = $group;
          }
        }
    }

    /**
     * Check for null and quote string.
     *
     * @param string|integer|null @value
     *
     * @return string
     */
    protected function fixValue($value) {
      return $value ? htmlspecialchars($value) : '';
    }

    /**
     * Export current favorites.
     *
     * @param integer $user_id
     *
     * @return RequestInterface
     */
    protected function export($user_id) {
      $user = Auth::user();
      $user_id = $user->id();

      header('content-type: text/x-gedcom; charset="UTF-8"');
      header('content-disposition: attachment; filename="favorites.csv"');
      $data[] = 'gedcom_id, xref, favorite_type, url, title, note';

      $result = DB::table('favorite')
            ->where ('user_id', '=', $user_id)
            ->get()
            ->toArray();

      if ($result) {
        foreach($result as $row) {
          $data[] = implode(',',[
            $row->gedcom_id,
            $row->xref,
            $row->favorite_type,
            $this->fixValue($row->url),
            $this->fixValue($row->title),
            $this->fixValue($row->note),
          ]);
        }
      }

      return response(implode("\n",$data),StatusCodeInterface::STATUS_OK);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
      $user = Auth::user();
      $user_id = $user->id();

      $tree = $request->getAttribute('tree');
      assert($tree instanceof Tree);

      $settings = $this->getSettings($user_id);
      $update = FALSE;

      // Set up for rendering and processing post.
      $favorites = [];
      $groups = [];
      $hash = [];
      $this->generateGroups($user_id, $favorites, $groups, $hash);

      // Handle POST request.
      if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
        $params = (array) $request->getParsedBody();
        $action = isset($params['action']) ? $params['action'] : '';
        switch($action) {
        case 'export':
          return $this->export($user_id);

        case 'import':
          if (isset($params['import']) && $params['import']) {
            $added = [];
            $error = [];
            $duplicate = [];
            $tree_service = app(TreeService::class);
            assert($tree_service instanceof TreeService);

            $rows = explode("\n",$params['import']);
            foreach($rows as $row) {
              [$gedcom_id, $xref, $favorite_type, $url, $title, $note] = 
                array_merge(
                  array_map(
                    fn($x) => trim(htmlspecialchars_decode($x)),
                    explode(',', $row)),
                  ['','','','','','']);

              $note = htmlspecialchars_decode($note);

              // Get list of active trees.
              $trees = [];
              foreach(DB::table('gedcom')
                ->where('gedcom_id', '!=', -1)
                ->get()
                ->toArray() as $gedcom) {
                $trees[$gedcom->gedcom_id] = $gedcom->gedcom_name;
              }

              // Ignore lines without a tree number. 
              if (is_numeric($gedcom_id)) {
                if (isset($trees[$gedcom_id])) {
                  switch($favorite_type) {
                    case 'URL':
                      $table = '';
                      $result = DB::table('favorite')
                        ->where('url', '=', $url)
                        ->get()
                        ->toArray();
                      if ($result) {
                        $duplicate[] = $row;
                      } else {
                        DB::table('favorite')->insert([
                          'user_id' => $user_id,
                          'gedcom_id' => $gedcom_id,
                          'favorite_type' => 'URL',
                          'url' => $url,
                          'title' => $title,
                          'note' => $note,
                        ]);
                        $added[] = $row;
                      }                      
                      break;
                    case 'INDI':
                      $table = "individuals";
                      $col_id = 'i_id';
                      $col_tree = 'i_file';
                      break;
                    case 'FAM':
                      $table = "families";
                      $col_id = 'f_id';
                      $col_tree = 'f_file';
                      break;
                    case 'OBJE':
                      $url = "media";
                      $col_id = 'm_id';
                      $col_tree = 'm_file';
                      break;
                    case 'SOUR':
                      $url = "sources";
                      $col_id = 's_id';
                      $col_tree = 's_file';
                      break;
                    case 'REPO':
                      $table = 'other';
                      $col_id = 'o_id';
                      $col_tree = 'o_file';
                      break;
                    default:
                      $table = '';
                      $error[] = I18N::translate('Invalid type') . ": $row";
                      break;
                  }

                  // Check GEDCOM items.
                  if ($table) {
                    $result = DB::table('favorite')
                      ->where('favorite_type', '=', $favorite_type)
                      ->where('xref', '=', $xref)
                      ->where('gedcom_id', '=', $gedcom_id)
                      ->get()
                      ->toArray();
                    if ($result) {
                      $duplicate[] = $row;
                    } else {
                      // Check if valid item.
                      $result = DB::table($table)
                        ->where($col_id, '=', $xref)
                        ->where($col_tree, '=', $gedcom_id)
                        ->get()
                        ->toArray();
                      if ($result) {
                        $result = DB::table('favorite')->insert([
                          'user_id' => $user_id,
                          'gedcom_id' => $gedcom_id,
                          'favorite_type' => $favorite_type,
                          'xref' => $xref,
                          'url' => $url,
                          'title' => $title,
                          'note' => $note,
                        ]);

                        $my_tree = $tree_service->find(intval($gedcom_id));
                        $gedcom = Registry::gedcomRecordFactory()->make($xref, $my_tree);
                        $added[] = $gedcom->fullName() . " == $row";
                      } else {
                        $error[] = I18N::translate('Missing') . ": $row";
                      }
                    }
                  }
                } else {
                  // Tree is missing.
                  $error[] = I18N::translate('Bad tree') . ": $row";
                }
              }
            }

            FlashMessages::addMessage(
              I18N::translate('Imported %s item(s). %s duplicates. %s error(s)', count($added), count($duplicate), count($error)));
            if ($added) {
              FlashMessages::addMessage(I18N::translate('Added') . '<br>' . implode('<br>',$added));
            }
            if ($duplicate) {
              FlashMessages::addMessage(I18N::translate('Duplicates') . '<br>' . implode('<br>',$duplicate));
            }
            if ($error) {
              FlashMessages::addMessage(I18N::translate('Errors') . '<br>' . implode('<br>',$error));
            }
          } else {
            FlashMessages::addMessage(
              I18N::translate('Nothing to import'));
          }

          $this->generateGroups($user_id, $favorites, $groups, $hash);
          break;

        case 'save':
          // Check secondary settings.
          if (isset($params['secondary'])) {
            $settings['secondary'] = [];
            foreach($params['secondary'] as $encoded) {
              $settings['secondary'][] = htmlspecialchars_decode($encoded);
            }

            $update = TRUE;
          }

          // Check URL text fields.
          if (isset($params['url'])) {
            $url_updates = [];
            foreach($params['url'] as $favorite_id => $url) {
              // Make sure text is there. Ignore if blank. Both fields must be set. 
              if (isset($params['url_title'][$favorite_id])) {
                if (  ($params['url'][$favorite_id] != $params['hidden_url'][$favorite_id])
                   || ($params['url_title'][$favorite_id] != $params['hidden_title'][$favorite_id])) {
                  $title = $params['url_title'][$favorite_id];
                  $url = $params['url'][$favorite_id];
                  $url_updates[] = $title;

                  // Update database.
                  DB::table('favorite')
                    ->where('favorite_id', '=', $favorite_id)
                    ->update([
                      'url' => $url,
                      'title' => $title,
                      ]);
                }
              }
            }
            if ($url_updates) {
              FlashMessages::addMessage(
                I18N::translate('Updated URL links') . '<br>' .
                implode('<br>',$url_updates));
              $this->generateGroups($user_id, $favorites, $groups, $hash);
            }
          }

          // Check active group radio buttons.
          $active_group = $params['active_group'];

          if ($params['new_group'] && $params['default_group']) {
            // Ignore radio selection if new group entered
            $active_group = $params['default_group'];
          } else {
            switch($active_group) {
              case 0:
                $active_group = '';
                break;
              case -1:
                $active_group = $params['default_group'];
                break;
              default:
                $record = $this->getFavorite($active_group);
                $active_group = $record ? ( $record->note ? $record->note : '' ) : '';
                break;
            }
          }

          // Handle default group rename.
          if ($params['rename_default']) {
            // Blank and null are the default group. 
            DB::table('favorite')
              ->where('gedcom_id', '=', $tree->id())
              ->where('user_id', '=', $user_id)
              ->where('note', '=', '')
              ->update(['note' => $params['rename_default']]);
            DB::table('favorite')
              ->where('gedcom_id', '=', $tree->id())
              ->where('user_id', '=', $user_id)
              ->where('note', '=', null)
              ->update(['note' => $params['rename_default']]);
            if (!$settings[default_group]) {
              $active_group = $params['rename_default'];
            }
          }
 
          // Make any group name changes.
          if (isset($params['group'])) {
            foreach ($params['group'] as $id => $group) {
              $group = htmlspecialchars_decode($group);
              $old_data = $this->getFavorite($id);
              $old_group = $old_data->note ? $old_data->note : '';
              if ($old_group != $group) {
                DB::table('favorite')
                  ->where('gedcom_id', '=', $tree->id())
                  ->where('user_id', '=', $user_id)
                  ->where('note', '=', $old_data->note)
                  ->update(['note' => ($group ? $group : null)]);
                if ($old_group == $settings['default_group']) {
                  $active_group = $group;
                }
              }
            }
          }


          // Update shared groups.
	  if (isset($params['shared'])) {
            $shared = [];
            foreach ($params['shared'] as $md5 => $value) {
              if ($md5) {
                if (isset($hash[$md5])) {
                  // Groupt title is key.
                  $shared[$md5] = $hash[$md5];
                  $update = TRUE;
                }
              } else {
                // Handle [].
                $shared[] = '';
                $update = TRUE;
              }
            }
            $this->setShared($user_id,$shared);
          }

          // Update settings is changes made.
          if ($update || ($settings['default_group'] != $active_group)) {
            $settings['default_group'] = $active_group;
            $this->setSettings($user_id, $settings);
          }

          // Check deletes.
          $regenerate = FALSE;
          $deleted = [];
          if (isset($params['delete'])) {
            foreach($params['delete'] as $favorite_id => $xref) {
              $deleted[$favorite_id] = $favorites[$favorite_id]['title'];

              // Remove from favorite. $xref ignored since it is empty for URLs. 
              DB::table('favorite')
                ->where('gedcom_id', '=', $tree->id())
                ->where('user_id', '=', $user_id)
                ->where('favorite_id', '=', $favorite_id)
                ->delete();
            }
            $regenerate = TRUE;
            FlashMessages::addMessage(
              I18N::translate('Deleted %s favorite(s).',count($params['delete'])) .
              '<br><div>' .
              implode('<br>',$deleted).
              '</div>');
          }

          // Check for moves. Array has empty entries.
          if (isset($params['move'])) {
            $move = [];
            foreach ($params['move'] as $favorite_id => $value) {
              if ('default' == $value) {
                $group = $settings['default_group'];
                $found = TRUE;
              } else if ($value && isset($hash[$value]) && !isset($deleted[$favorite_id])) {
                $group = $hash[$value];
                $found = TRUE;
              } else {
                $found = FALSE;
              }

              if ($found) {
                $move[] =  I18N::translate(
                  "%s moved to [%s]",
                  $favorites[$favorite_id]['title'],
                  $group ? $group : ' '
                  );

                DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('user_id', '=', $user_id)
                    ->where('favorite_id', '=', $favorite_id)
                    ->update(['note' => ($group ? $group : null)]);
              }
            }
            if ($move) {
              $regenerate = TRUE;
              FlashMessages::addMessage(
                I18N::translate('Moved %s favorite(s).',count($move)) .
                '<br><div>' .
                implode('<br>',$move).
                '</div>');
            }
          }

          // See if URLs have been added.
          $urls = [];
          foreach($params['text'] as $key => $value) {
              if ($value
               && isset($params['url'][$key])
               && $params['url'][$key]) {
                $regenerate = TRUE;
                $group = $hash[$key];
                DB::table('favorite')->insert([
                  'user_id' => $user_id,
                  'gedcom_id' => $tree->id(),
                  'title' => $value,
                  'favorite_type' => 'URL',
                  'url' => $params['url'][$key],
                  'note' => ($group ? $group : null)]
                );
              }
          }

          if ($regenerate) {
            // Regenerate arrays that have now changed. 
            $this->generateGroups($user_id, $favorites, $groups, $hash);
          }

          // Show status.
          FlashMessages::addMessage(I18N::translate('Settings saved.'));
          break;
        }
      }

      // Get secondary list.
      $secondary[","] = '-- ' . I18N::translate('Show no secondary menu') . ' --';
      foreach($groups as $group => $info) {
        $secondary["$user_id,$group"] = I18N::translate('Yours') . ' -- '.
            ($group ? $group : '[ ]');
      }

      // Check for other user's shared lists.
      $result = DB::table('user_setting')
          ->where ('setting_name', '=', 'favorites-menu-shared')
          ->where ('user_id', '!=', $user_id)
          ->get()
          ->toArray();

      if ($result) {
        $user_service = app(UserService::class);
        assert($user_service instanceof UserService);

        $extra = [];
        foreach($result as $row) {
          $check_user = $user_service->find(intval($row->user_id));
          if ($check_user) {
            foreach(unserialize($row->setting_value) as $group) {
             $extra["$row->user_id,$group"] = $check_user->realName() . ' -- '.
               ($group ? $group : '[ ]');
            }
          }
        }

        if ($extra) {
          asort($extra);
          foreach($extra as $key => $value) {
            $secondary[$key] = $value;
          }
        }
      }

      return $this->viewResponse($this->name() . '::favorites', [
            'tree'          => $tree,
            'title'         => I18N::translate('Favorites Menu Settings'),
            'module'        => $this->name(),
            'is_admin'      => Auth::isAdmin(),
            'settings'      => $settings,
            'favorites'     => $favorites,
            'my_this'       => $this,
            'groups'        => $groups,
            'secondary'     => $secondary,
            'shared'        => $this->getShared($user_id)
        ]);
    }

    /**
     * Generate an HTML select for group move.
     *
     * @param string $default_group
     *
     * @param array $groups
     *   Array of groups.
     * @param string $current_group
     *   Name of current group.
     * @param string $id
     *
     * @return string
     *   String with HTML select definition. 
     */
    public function getGroupSelect($default_group,$groups,$current_group,$id) {
      $options[] = '<option value>-- ' . I18N::translate('Move to') . ' --</option>';
      $missing = TRUE;
      foreach($groups as $group => $item) {
        if ($default_group == $group) {
          $missing = FALSE;
        }
        if ($group != $current_group) {
          $title = htmlspecialchars($group ? $group : '[ ]');
          $md5 = $item['md5'];
          $options[] = "<option value='$md5'>$title</option>";
        }
      }

      if ($missing) {
        $title = htmlspecialchars($default_group);
        $options[] = "<option value='default'>$title</option>";
      }
      $options_string=implode('',$options);
      echo "<select name='move[$id]'>$options_string</select>";
    }
};
