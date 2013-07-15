<?php
/*
  Text Publisher bundle for Laravel | https://github.com/ProgerXP/TextPub
  in public domain |  by Proger_XP  | http://i-forge.net

  Used as a backend on Laravel.ru.
*/

class TextPub {
  // regular expression used to extract document title from the formatted HTML.
  static $title_regexp = '~<([hH][123456])\b[^>]*>(.+?)</\1>~s';

  // configured text page paths to be served.
  static $paths = array();

  // retrieves configuration option.
  static function option($name, $default = null) {
    return Config::get("textpub::general.$name", $default);
  }

  // retrieves all configuration options.
  static function options() {
    return Config::get('textpub::general');
  }

  // sets paths to be served.
  static function paths($paths) {
    static::$paths = static::norm_paths($paths);
  }

  // normalizes array of paths to be served - adds optional fields, etc.
  static function norm_paths($paths) {
    $norm = array();

    foreach ((array) $paths as $url => $info) {
      if (is_int($url)) {
        $url = $info;
        $info = array('path' => $info);
      } else {
        $info = (array) $info;
      }

      if (array_get($info, 'as', true) === true) {
        $info['as'] = static::route_name($url);
      }

      if (!isset($info['single'])) {
        $merged = $info + static::options();
        $info['single'] = is_array(static::find('', $info['path'], $merged['ext']));
      }

      $norm[] = $info + compact('url');
    }

    return $norm;
  }

  // returns named route name for a given text page/group URL.
  static function route_name($url) {
    return Str::slug(trim($url, '/'), '_');
  }

  /*
   * Registers configured paths as routes so that they will be served.
   * Routes are registered for all available HTTP methods so that served
   * pages can include forms. Adds some fields to each registered path:
     - root - complete URL including the base URL specified by 'handles'
     - callback - the callback Closure that was assigned to the route
   */
  static function register($root = '', $paths = null) {
    isset($paths) and static::paths($paths);

    foreach (static::$paths as $key => &$path) {
      $self = get_called_class();

      $path = array(
        'root'      => $root.$path['url'],
        'callback'  =>
          function ($page = null) use ($self, $key) {
            $request = strtok(array_get($_SERVER, 'REQUEST_URI'), '?');

            if ("$page" !== '' and substr($request, -1) === '/') {
              // forces check for directory index in serve().
              $page .= '/';
            }

            return $self::serve_by($key, $page);
          },
      ) + $path;

      Route::get($path['root'], $path);

      if (empty( $path['single'] )) {
        Route::any($path['root'].'/(:all)', $path);
      }
    }
  }

  /*
   * Returns URLs of all registered text pages/groups including the
   * base URL assigned to TextPub using 'handles' (if any).
   */
  static function urls() {
    return array_map(function ($path) { return $path['root']; }, static::$paths);
  }

  // writes a log message of given priority.
  static function log($msg, $level = 'warning') {
    Log::$level("Text Publisher (textpub) $msg");
  }

  /*
   * Returns a formatted version of the configured text page by its key in
   * static::$paths. $page is used for groups of pages to specify which page
   * to serve - if not given it will be detected based on the current Request.
   * Returns 404 in case the page couldn't be found.
   */
  static function serve_by($key, $page = null) {
    $info = array_get(static::$paths, $key);
    if (!$info) {
      static::log("could not find path [$key] - returning 404.");
      return Event::until(404);
    } else {
      $response = static::serve($info, $page);

      if ($response === false) {
        static::log("could not find page [$page] on [$info[url]] - returning 404.");
        $response = static::not_found($info, $page);
      }

      return $response;
    }
  }

  /*
   * Returns a formatted version of the text page specified by $info.
   * This is similar to serve_by() - see its description for details.
   * Returns FALSE if the page couldn't be found.
   */
  static function serve(array $info, $page = null) {
    $info += static::options();

    if (empty($info['single'])) {
      isset($page) or $page = static::page_by( array_get($info, 'root') );

      if ("$page" === '' or substr($page, -1) === '/') {
        $page .= array_get($info, 'index', 'index');
      }
    } else {
      $page = '';
    }

    $content = static::get($page, $info);

    if (!isset($content)) {
      return false;
    } else {
      if (!empty($info['debug'])) {
        static::log("is serving page [$page] on [$info[url]].", 'debug');
      }

      return static::render($content, $info);
    }
  }

  /*
   * Attempts to format a custom 404 page by looking in the requested page's
   * path, then one level up, etc. Returns standard 404 response if none found.
   */
  static function not_found(array $info, $page) {
    $page404 = static::option('404', '_404');
    $response = null;

    if (is_callable($page404)) {
      $response = call_user_func($page404, $info, $page);

      if (is_array($response)) {
        $response = static::render($response, $info + static::options());
      }
    } elseif ($page404 !== false) {
      $info = array('cache' => false, 'page_404' => $page) + $info;
      $parts = explode('/', str_replace('\\', '/', $page));

      do {
        array_pop($parts);
        $error = join(DS, $parts).DS.$page404;
        $response = static::serve($info, $error);
      } while ($parts and !$response);

      $response and $response = static::format_404($response, $info, $page, $error);
    }

    if (!isset($response) or $response === false) {
      $response = Event::until(404);
    }

    return $response;
  }

  // replaces variables in the 404 page with the info about current request.
  static function format_404($str, array $info, $page, $error_page) {
    $replaces = array(
      '%404_URL%'       => URL::current(),
      '%404_LANG%'      => static::lang(),
      '%404_ROOT%'      => static::relative_path($page, $error_page),
      '%404_PATH%'      => $info['root']."/$page",
      '%404_PAGE%'      => $page,
      '%404_REFERRER%'  => Request::referrer(),
    );

    foreach ($replaces as $key => $value) {
      $replaces[ substr($key, 0, -1).'Q%' ] = HTML::entities($value);
    }

    $replaces = array_map('e', $replaces);
    return strtr((string) $str, $replaces);
  }

  /*
   * Attempts to return the version of absolute $dest path which is relative
   * to the passed $base. For example, if:
   *
   *   - $base = http://site.ru/textpub/path
   *   - $dest = http://site.ru/textpub/path/sub/page
   *
   * ...this method will return '../'
   */
  static function relative_path($base, $dest) {
    $base = trim($base, '\\/');
    $dest = trim($dest, '\\/');

    $relative = $dest;

    for ($i = 0; ; ++$i) {
      if (!isset($base[$i])) {
        $relative = '';
        break;
      } elseif ($base[$i] !== substr($dest, $i, 1)) {
        $relative = substr($base, $i);
        break;
      }
    }

    $relative = trim(str_replace('\\', '/', $relative), '/');
    return str_repeat('../', substr_count($relative, '/'));
  }

  /*
   * Returns the name of requested page residing under $root. If $root is given
   * and the request doesn't belong to it NULL is returned.
   * For example, if Request::uri() = '/root/sub/page/' then:
   *
   *   - page_by('/root/')        => 'sub/page'
   *   - page_by(null)            => 'root/sub/page'
   *   - page_by('/differs/')     => NULL
   */
  static function page_by($root) {
    $page = trim(Request::uri(), '/');

    if (isset($root)) {
      foreach ((array) $root as $url) {
        $url = trim($url, '/');
        @list($prefix, $tail) = explode("/$url/", "/$page/", 2);

        if ($prefix === '' and isset($tail)) {
          return trim($tail, '/');
        }
      }
    } else {
      return $page;
    }
  }

  /*
   * Returns formatted version of $page belonging to configuration $info or NULL
   * if it doesn't exist. If configured, current cache driver will be used to
   * store and quickly retrieve formatted pages on subsequent requests.
   *
   * Sets $info['file'] to the full path to the text page file or to NULL on 404;
   * sets $info['base'] to the base directory with trailing directory separator.
   */
  static function get($page, array &$info) {
    $id = static::lang()."@$info[url]>$page";
    $found = static::find($page, $info['path'], $info['ext']);

    if (is_array($found)) {
      list($file, $base) = $found;
    } elseif ($found === true) {
      if (empty($info['redirToSlash'])) {
        $index = array_get($info, 'index', 'index');
        @list($file, $base) = static::find($page.DS.$index, $info['path'], $info['ext']);
      } else {
        $query = $_GET ? '?'.http_build_query($_GET) : '';
        return Redirect::to("$info[root]/$page/$query", 301);
      }
    } else {
      $file = $base = null;
    }

    $info += compact('file', 'base');
    $cache = $info['cache'];

    if (is_array($cache)) {
      $format = File::extension($file);

      $cache = array_get($info, "cache.$format");
      isset($cache) or $cache = array_get($info, 'cache.0', 60);
    }

    if (!$file) {
      $cache and static::cache($id, false);
    } else {
      list($gen_time, $content) = $cache ? static::cache($id) : array(0, '');

      if (static::expires($file, $gen_time)) {
        $formatter = array_get($info['ext'], File::extension($file));
        // not passing $info['formatter'] as $default parameter for array_get()
        // because the latter calls value() on it and if the formatter is a closure
        // it will be called - which is not intended.
        isset($formatter) or $formatter = $info['formatter'];

        $content = static::format(File::get($file), $info, $formatter);

        isset($content) and static::cache($id, $content, $cache);
      }

      return $content;
    }
  }

  // returns TRUE if page file $file is of more recent version than its cache.
  static function expires($file, $gen_time) {
    return filemtime($file) > $gen_time;
  }

  /*
   * Returns the full path to page $page residing under base directory $path
   * and of one of the allowed $extensions. Returns NULL if none was found.
   */
  static function find($page, $path, $extensions) {
    $files = static::files_of($page, $path, $extensions);

    foreach ($files as $base => &$file) {
      if (is_dir($file)) {
        return true;
      } elseif ($file = static::file_of($file, $extensions)) {
        return array($file, $base);
      }
    }
  }

  /*
   * Returns possible locations of the (relative) page file $page.
   * $extensions is the list of configured formatters/file extensions
   * and can be used to alter the location in some way based on them -
   * for example, .html can be placed in public/*.html while .md - in
   * storage/*.md.
   */
  static function files_of($page, $path, $extensions) {
    $page = rtrim($page, '\\/');

    $path = rtrim($path, '\\/');
    $path === '' or $path .= DS;

    $files = array();

    if (static::is_relative($path)) {
      $locations = array('languages'.DS.static::lang(),
                         'storage'.DS.static::lang(),
                         'storage',
                         'public'.DS.static::lang(),
                         'public');

      foreach ($locations as $base) {
        $base = Bundle::path('textpub').$base.DS;
        $files[$base] = rtrim($base.$path.$page, '\\/');
      }
    } else {
      $files[$path] = static::format_path($path).$page;
    }

    return $files;
  }

  // formats 'path' option with supported replacements.
  static function format_path($path) {
    $replaces = array(
      ':bundle'       => Bundle::path('textpub'),
      ':lang'         => static::lang(),
      ':page'         => trim($path, '/'),
    );

    return strtr($path, $replaces);
  }

  // returns TRUE if given $path is relative, FALSE otherwise.
  static function is_relative($path) {
    return strpbrk($path[0], '\\/') === false and substr($path, 1, 1) !== ':';
  }

  /*
   * Attempts to find text page file $basename. Works in two modes:
   *   - if $basename has any extension - just checks if a file with this
   *     name exists - returns NULL if it doesn't or $basename otherwise
   *   - otherwise, and if there are any $extensions, uses each of them
   *     in turn to find extensionless $basename - returns full name upon
   *     success or NULL if none were found
   */
  static function file_of($basename, $extensions = null) {
    if (File::extension($basename) or !$extensions) {
      return is_file($basename) ? $basename : null;
    } else {
      foreach ((array) $extensions as $ext => $formatter) {
        is_int($ext) and $ext = $formatter;

        if ($file = static::file_of( "$basename.".ltrim($ext, '.') )) {
          return $file;
        }
      }
    }
  }

  /*
   * Formats given string (page markup) according to the formatter and
   * using page/path information. $info contains all the fields registered
   * paths have plus those added by various methods the request has went
   * through before, such as 'root', 'callback' and 'file'.
   *
   * Returns either a formatted string (presumably HTML) or an array that
   * is then passed to the view to be formatted (see render()). Might also
   * return NULL if the formatting has failed for some reason albeit the
   * $formatter string was valid and processed.
   */
  static function format($str, array $info, $formatter) {
    if (is_array($str)) {
      foreach ($str as &$s) { $s = static::format($s, $info, $formatter); }
      return $str;
    } elseif (isset($str)) {
      if (is_callable($formatter)) {
        $str = call_user_func($formatter, $str, $info);
      } elseif ($formatter === 'raw') {
        $str = (string) $str;
      } elseif ($formatter === 'php') {
        $vars = array();
        extract($info, EXTR_SKIP);

        ob_start();
        eval("?>$str");
        $str = $vars + array('content' => ob_get_clean());
      } elseif (is_string($formatter)) {
        $type = trim(strtok($formatter, ':'));
        $class = trim(strtok('->'));
        $method = trim(strtok(null) ?: 'format', '> ');

        switch ($type) {
        case 'class':   break;
        case 'ioc':     $class = IoC::resolve($class, array($str, $info)); break;
        case 'event':   $class = Event::first($class, array($str, $info)); break;
        default:        throw new Exception("Text Publisher: invalid 'formatter' string value.");
        }

        if ($type === 'class' or is_object($class)) {
          $str = call_user_func(array($class, $method), $str, $info);
        } else {
          $str = $class;
        }
      } else {
        throw new Exception("Text Publisher: invalid 'formatter' value type.");
      }

      return ($str !== null and $str !== false) ? $str : null;
    }
  }

  // stores, retrieves or removes the cache item based on its $id.
  static function cache($id, $content = null, $expire = 0) {
    if (isset($content)) {
      if ($expire > 0) {
        $expire === true and $expire = 30 * 24 * 60;

        $cache = array(time(), $content);
        Cache::put(static::cache_id($id), $cache, $expire);
      }
    } elseif ($content === false) {
      Cache::forget(static::cache_id($id));
    } else {
      $cache = Cache::get(static::cache_id($id));
      return is_array($cache) ? $cache : null;
    }
  }

  // generates TextPub cache item ID for current cache driver.
  static function cache_id($id) {
    return 'textpub_'.md5($id);
  }

  /*
   * Renders a formatted page (array or string, see format()) according to
   * its configuration ($info). Returns a string or an object (like View).
   */
  static function render($content, array $info) {
    if ($content instanceof Laravel\Response) {
      return $content;
    } else {
      $content = static::norm_rendered($content);
      $layout = array_get($info, 'layout', 'textpub::text');

      if (is_string($layout)) {
        return View::make($layout)->with($content);
      } elseif (is_callable($layout)) {
        return call_user_func($layout, $content, $info);
      } elseif ($layout instanceof View) {
        return $layout->with($content);
      } elseif (method_exists($layout, 'render')) {
        return $layout->render($content);
      } else {
        throw new Exception("Text Publisher: invalid 'layout' value for $info[url].");
      }
    }
  }

  // adds useful fields to the formatted $data to be passed to the page renderer.
  static function norm_rendered($data) {
    is_array($data) or $data = array('content' => $data);

    $data += array(
      'lang'      => static::lang(),
      'charset'   => Config::get('application.encoding'),
      'head'      => '',
    );

    if (!isset($data['title'])) {
      $data['title'] = static::title_from($data['content']);
    }

    return array_map('value', $data);
  }

  /*
   * Extracts title from the formatted HTML; uses UTF-8 mode, then fallbacks
   * to ASCII and finally returns NULL if no title could be found.
   */
  static function title_from($html) {
    if (preg_match(static::$title_regexp.'u', $html, $match)) {
      return $match[2];
    } elseif (preg_last_error() and preg_match(static::$title_regexp, $html, $match)) {
      return $match[2];
    }
  }

  // returns current request's language.
  static function lang() {
    return Cookie::get('textpub.language', Config::get('application.language'));
  }
}