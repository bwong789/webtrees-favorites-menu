<?php

declare(strict_types=1);

namespace favorites_menu\Webtrees\Module\FavoritesMenu;

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
    public const CUSTOM_VERSION = '1.5';
    public const GITHUB_REPO = 'webtrees-favorites-menu';

    public const AUTHOR_WEBSITE = 'https://github.com/bwong789'; //'https://none.com';
    public const CUSTOM_SUPPORT_URL = 'https://github.com/bwong789/webtrees-favorites-menu/issues'; //self::AUTHOR_WEBSITE . '/none/';

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
     * @param User $user
     * @param Tree $tree
     *
     * @return array
     *   Array of arrays with keys: group, title, type, url.
     */
    public function getFavorites($user, Tree $tree):array {
      $uri = $_SERVER['REQUEST_URI'];
      $path = explode('/',$uri);
      $tree_index = array_search('tree',$path);
      $tree_name = $tree->name();
      $user_id = $user->id();

      $path_prefix = array_slice($path,0,$tree_index);
      if ($path_prefix) {
        $url_prefix = implode('/',array_slice($path,0,$tree_index));
      } else {
        $url_prefix = '';
      }

      $result = [];
      $favorites = (new UserFavoritesModule())->getFavorites($tree,$user);


      foreach ($favorites as $favorite) {
        $ftype = $favorite->favorite_type;

        if ($ftype != 'URL') {
          $id = $favorite->xref;
          switch ($ftype) {
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
          $result[$favorite->favorite_id] = [
            'favorite_id' => $favorite->favorite_id,
            'title' => $favorite->record->fullName(),
            'type' => $ftype,
            'url' => "$url_prefix/tree/$tree_name/". $url,
            'group' => $favorite->note ? $favorite->note : '',
            'xref' => $favorite->xref];
        }
      }

      // Get any URL entries.
      $url_favorites = DB::table('favorite')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $user->id())
            ->where('favorite_type', '=', 'URL')
            ->get()
            ->all();

      foreach($url_favorites as $url_favorite) {
        $result[$url_favorite->favorite_id] = [
          'favorite_id' => $url_favorite->favorite_id,
          'title' => $url_favorite->title,
          'type' => 'URL',
          'url' => $url_favorite->url,
          'group' => $url_favorite->note ? $url_favorite->note : '',
          'xref' => '',
        ];
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

        $tree_name = $tree->name();
        $uri = $_SERVER['REQUEST_URI'];
        $path = explode('/',$uri);
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
              default:
                $gedcom_type = '';
                break;
          }
          $xref = $path[$tree_index + 3];
        } else {
          $gedcom_type = '';
        }

        $group = $this->getGroup($user_id);


        // Get current favorites setting. 
        if ($gedcom_type) {
          $result = DB::table('favorite')
            ->where('gedcom_id', '=', $tree->id())
            ->where('user_id', '=', $user_id)
            ->where('favorite_type', '=', $gedcom_type)
            ->where('xref', '=', $xref)
            ->get()
            ->toArray();

          $my_group = $result ? $result[0]->note : '';
          $parameters = explode('&',$_SERVER['QUERY_STRING']);
          $args = [];
          foreach ($parameters as $i => $value) {
            switch ($value) {
              case 'favorites-menu-move':
                // Change group
                DB::table('favorite')
                    ->where('gedcom_id', '=', $tree->id())
                    ->where('user_id', '=', $user_id)
                    ->where('favorite_type', '=', $gedcom_type)
                    ->where('xref', '=', $xref)
                    ->update(['note' => ($group ? $group : null)]);
                FlashMessages::addMessage(
                    I18N::translate('Favorite moved to group: ') .
                    "[$group]");
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
                    'note' => ($group ? $group : null)]);
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

          // Setup display and url parameter. 
          if ($result) {
              if ($group != $my_group) {
                $move_args = $args;
                $move_args[] = 'favorites-menu-move';
                $move_args = '?' . implode('&',$move_args);
              }
              $class = 'favorites-menu-true';
              $prefix = '[*] ';
              $args[] = 'favorites-menu-false';
              $action = 'Remove favorite';
          } else {
              $class = 'favorites-menu-false';
              $prefix = '[ ] ';
              $args[] = 'favorites-menu-true';
              $action = 'Add favorite';
          }
        } else {
          $class = 'favorites-menu-false';
          $prefix = '';
          $args = [];
          $action = '';
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

        $group = $this->getGroup($user_id);
        $submenu[] = new Menu(
           htmlspecialchars($group),
           e( "$url_prefix/tree/$tree_name/favorites-menu"),
           "favorites-menu-group favorites-menu-item");

        if ($action) {
          $my_url = explode('?',$uri)[0];
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

        $default_group = $this->getGroup($user_id);

        $favorites = $this->getFavorites($user, $tree);
        foreach(['INDI', 'FAM', 'OBJE', 'URL', 'NOTE', 'SOUR', 'REPO' ] as $type) {
          foreach($this->getFavorites($user, $tree) as $favorite) {
            if (($type == $favorite['type']) && ($default_group == $favorite['group'])) {
              $submenu[] = new Menu(
                $favorite['title'],
                e($favorite['url']),
                "favorites-menu-$type favorites-menu-item");
            }
          }
        }

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
     * Get default group
     *
     * @param integer $user_id
     *
     * @return string
     */
    public function getGroup($user_id): string {
      $result = DB::table('user_setting')
            ->where('setting_name', '=', 'favorites-menu-default')
            ->where('user_id', '=', $user_id)
            ->get()
            ->toArray();

      return $result ? $result[0]->setting_value : '';
    }

    /**
     * Set default group
     *
     * @param integer $user_id
     * @param string  $group
     */
    public function setGroup($user_id, $group) {
      // Check if entry is in the table.
      $result = DB::table('user_setting')
            ->where('setting_name', '=', 'favorites-menu-default')
            ->where('user_id', '=', $user_id)
            ->get()
            ->toArray();

      if ($result) {
        $result = DB::table('user_setting')
               ->where('setting_name', '=', 'favorites-menu-default')
               ->where('user_id', '=', $user_id)
               ->update(['setting_value' => $group]);
      } else {
        $result = DB::table('user_setting')->insert([
                    'user_id' => $user_id,
                    'setting_name' => $gedcom_type,
                    'setting_value' => $group]);
      }
    }

    /**
     * Generate groups and hashs. 
     *
     * Parameter arrays are cleared and regenerated. 
     *
     * @param User  $user
     * @param Tree  $tree
     * @param array &$favorites
     * @param array &$groups
     * @param array &$hash
     */
    protected function generateGroups($user, $tree, &$favorites, &$groups, &$hash) {
        $favorites = $this->getFavorites($user,$tree);
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

        $default_group = $this->getGroup($user_id);

        // Set up for rendering and processing post.
        $favorites = [];
        $groups = [];
        $hash = [];
        $this->generateGroups($user, $tree, $favorites, $groups, $hash);

        // Handle POST request.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
          // Check radio buttons.
          $params = (array) $request->getParsedBody();
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
            if (!$default_group) {
              $active_group = $params['rename_default'];
            }
          }
 
          // Make any group name changes.
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
              if ($old_group == $default_group) {
                $active_group = $group;
              }
            }
          }

          // Update default group.
          if ($default_group != $active_group) {
            $default_group = $active_group;
            $this->setGroup($user_id, $default_group);
          }

          // Check deletes.
          $regenerate = FALSE;
          $deleted = [];
          if (isset($params['delete'])) {
            foreach($params['delete'] as $favorite_id => $xref) {
              $deleted[$favorite_id] = $favorites[$favorite_id]['title']."-$favorite_id-";

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
              if ($value && isset($hash[$value]) && !isset($deleted[$favorite_id])) {
                $group = $hash[$value];
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
            $this->generateGroups($user, $tree, $favorites, $groups, $hash);
          }

          // Show status.
          FlashMessages::addMessage(I18N::translate('Settings saved.'));
        }

        return $this->viewResponse($this->name() . '::favorites', [
            'tree'          => $tree,
            'title'         => I18N::translate('Favorites Menu Settings'),
            'module'        => $this->name(),
            'is_admin'      => Auth::isAdmin(),
            'default_group' => $default_group,
            'favorites'     => $favorites,
            'my_this'       => $this,
            'groups'        => $groups,
        ]);
    }

    /**
     * Generate an HTML select for group move.
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
    public function getGroupSelect($groups,$current_group,$id) {
      $options[] = '<option value>' . I18N::translate('Change to move') . '</option>';
      foreach($groups as $group => $item) {
        if ($group != $current_group) {
          $title = htmlspecialchars($group ? $group : '[ ]');
          $md5 = $item['md5'];
          $options[] = "<option value='$md5'>$title</option>";
        }
      }
      $options_string=implode('',$options);
      echo "<select name='move[$id]'>$options_string</select>";
    }
};
