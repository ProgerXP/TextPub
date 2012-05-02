<?php
/*
 * TextPub configuration is based on the concept of 'paths' and is twofold:
 * global options are specified under the returned array() - they are used
 * as default values; path-specific options are set in global 'paths' array
 * option and keys appearing there override global ones.
 */

return array(
  /*
   * List of page extensions and their formatters. Each item can be of two forms:
   *   - 'ext' => <formatter> - this will override default formatter option for
   *     pages having .ext extension; see 'formatter' description for allowed values
   *   - 'ext' (no key) - pages with this extension will be formatted using the
   *     default formatter (either specified globally or per some path)
   *
   * Extension might have leading period ('.ext') which is ignored.
   *
   * This option can also be a single string (e.g. 'ext') that is automatically
   * converted to array('ext') and follows regular rules explained above.
   */
  'ext' => array('txt', 'html' => 'raw', 'php' => 'php'),

  /*
   * Default formatter used to convert page data read from the file into HTML
   * to be given to the visitor. Can have the following values:
   *   - string 'raw' - this does no transformation and outputs the page exactly
   *     as read from the file; useful for HTML pages
   *   - string 'php' - executes read string as PHP code; think of this as if
   *     the page was include()d and its output placed into the 'content' variable
   *     passed to the view later. These variables are set in the included scope:
   *     - $vars - if there's some data apart from 'content' you wish to pass to
   *       the view - place them here, for example:  $vars['title'] = 'Page title';
   *     - all current path options are available - $layout, $index, $as, etc.
   *    - $file - absolute path to the page file
   *   - callable function ($content, array $info) - $content is a string read
   *     from the page file; $info is current page/path info - see the description
   *     of 'php' formatter above for the keys it contains
   *   - string 'class: Class->method' - invokes Class::method passing it the same
   *     arguments given to the callable (see above)
   *   - string 'class: Class' - the same but implies 'format' method name
   *   - string 'ioc: resolver[->method]' - calls IoC::resolve('resolver') and if:
   *     - it returns a string - uses it as the formatted value
   *     - it returns an object - invokes given 'method' ('format' if omitted) on it
   *   - string 'event: name[->method]' - exactly the same as 'ioc:' but instead
   *     of calling IoC uses Event::first()
   *
   * Any other value raises an exception. Return value of the formatter is not
   * checked and should be either NULL/FALSE (implies NULL), an array (view
   * template variables) or a string (converted to single 'content' view variable).
   *
   * NOTE: 'php' formatter is potentially dangerous if your text page directory is
   *       publicly available for writing as it allows executing arbitrary PHP code.
   */
  'formatter' => array('HTML', 'entities'),

  /*
   * Specifies how the resulting page must be formatted - can be a string (name of
   * the view, e.g. "layouts/textpub") or a callable function (array $vars, array $info)
   * where $info contains all the path info (see 'formatter' option above) and
   * $vars is the result of formatting the page with at least these keys:
       - content  - HTML code (or other, depends on the formatter used)
       - lang     - current request or application language ,see TextPub::lang()
       - charset  - the value of application.charset; useful for the <meta> tag
       - head     - a place to store extra HTML to be appended to <head> - useful
                    for including stylesheets and JavaScript files
      - title     - if formatter haven't set this it is set to the value of <h1> tag
                    (h2, h3, etc. - whatever goes first) or to NULL if none found
   */
  'layout' => 'textpub::text',

  // name of the page to display when a path is requested by its root URL
  'index' => 'index',

  /*
   * Name of the page shown when TextPub cannot locate requested page. Can be
   * FALSE to show system 404 page, a string - page name to search or a callable
   * function (array $info, $req_page) that returns a regular Laravel response
   * (View, Response, string, etc.).
   *
   * For example, if http://site.ru/path/sub/page is requested and not found
   * and this option has value '_404" the following locations are attempted:
   *   - /TEXTPUB_ROOT/path/sub/_404
   *   - /TEXTPUB_ROOT/path/_404
   *   - /TEXTPUB_ROOT/_404
   *
   * 404 page follows normal page's rules including extensions defined in 'ext'.
   * After formatting the following %VARIABLES% are replaced (with HTML quoted):
   *   - %404_URL%        - full request URL - URL::current()
   *   - %404_LANG%       - current language (TextPub::lang())
   *   - %404_ROOT%       - relative path from the requested page to this 404
   *                        page or '' if they're on the same level (see below)
   *   - %404_PAGE%       - URL path to the requested page, e.g. '/sub/non-existing'
   *   - %404_PATH%       - like %404_PAGE% but includes 'handles' path if it's set
   *   - %404_REFERRER%   - corresponds to Request::referrer()
   *
   * %404_ROOT% is useful to refer to the 404 page's resources since the client
   * might have requested a page residing several levels below itself. For example,
   * if 'http://site.ru/root/sub/page' has caused 404 page at /root/_404 to be
   * returned %404_ROOT% will be '../' because 'page' is located under sub/
   * directory of the root/ directory which in turn contains that 404 page.
   *
   * On the contrary, if _404 is located in /root/sub then %404_ROOT% will be ''.
   *
   * Consider this (if _404 is in HTML format) for an example;
   *
   *   <img src="%404_ROOT%/_404.png" alt="Not Found" />
   *
   * This allows avoiding absolute URLs or calling URL/URI methods from 404 pages.
   */
  '404' => '_404',

  /*
   * Controls caching mechanism; is an array where the first element (without
   * a key) defines default policy and all others are 'ext' => <state> pairs,
   * where <state> can be TRUE to cache for 30 days, FALSE to disable caching or
   * an integer value specifying the expiration time (in minutes).
   *
   * Cache is always discarded if the page file has changed after it was cached.
   * Also, for 404 pages (see the '404' option) caching is always disabled.
   *
   * This option can also be a non-array which is equivalent to array(<value>),
   * for example: 'cache' => 60 is the same as 'cache' => array(60) and caches
   * all pages regardless of their extension for 60 minutes.
   */
  'cache' => array(60 * 24, 'html' => false, 'php' => false),

  // if TRUE, TextPub will log some debug messages using Log::debug().
  'debug' => Laravel\Request::is_env('local'),

  /*
   * Instructs TextPub to register named routes when registering text pages in
   * the Router. If TRUE, will convert page's URL into a name using Str::slug('_');
   * if FALSE will use unnabled routes; if string - will register under that name.
   *
   * This option is particularly useful when overriden on per-path basis.
   */
  'as' => true,

  // specifies whether a single document or a directory resides on the page 'path'.
  'single' => false,

  /*
   +--------------------------------------------------------------------------
   | Paths Configuration
   +--------------------------------------------------------------------------
   * This is an array of locations ('paths') to be served by Text Publisher.
   * If you specify 'handles' for 'textpub' in your application/bundles.php
   * then they all are automatically placed under that URL, otherwise they're
   * registered under site root.
   *
   * You can omit 'paths' from here and include 'handles' to effectively enable
   * TextPub serve all pages coming to its bundle root URL (specified by
   * 'handles'). This is also most performance-aware setup because this bundle
   * will only be started upon request.
   *
   * Note that you must set either 'handles', 'paths' or both or an error occurs.
   *
   * This array is a set of 'url[/path...]' => <value> pairs where key
   * corresponds to the URL to be served (prefixed with 'handles' automatically
   * if it's specified) and <value> is an array of all the same keys allowed in
   * the global configuration options (examplained above in this file). Such
   * options ("per-path options") override global ones with the same name.
   *
   * There are also the following new options each path may contain:
   *   - path - the location of the text page or a directory ("group") of pages;
   *     can be absolute (starting with "/" or a drive letter) or relative - see below
   *   - single - boolean value indicating that this is a single page rather
   *     than a directory; if not set will be detected automatically
   *
   * Additionally, items in the 'path' option can be missing keys - in this
   * case they are converted to 'url/...' => array('path' => 'url/...').
   *
   * Also, the entire 'paths' value can be a string - it's converted into
   * array('url/...') and then into array('url/...' => array('path' => 'url/...')).
   *
   +--------------------------------------------------------------------------
   | Sample Configuration
   +--------------------------------------------------------------------------
   *
   * Contents of TEXTPUB_ROOT/storage:
   *   - docs/api/Controller.html
   *   - docs/basics.html
   *   - docs/classes.html
   *   - docs/index.html
   *   - _404.html
   *   - index.html
   *   - license.txt
   *
   * If we assume that all options have default values and 'ext' is:
   *
   *   array('txt' => array('HTML', 'entities'), 'html' => 'raw')
   *
   * Then the following two 'paths' configurations are identical:
   *
   *   array('docs', 'index', 'license')
   *
   *   array(
   *     'docs'     => array('path' => 'docs'),
   *     'index'    => array('path' => 'index', 'single' => true),
   *     'license'  => array('path' => 'license', 'single' => true)
   *   )
   *
   * We can override global settings on the per-path basis:
   *
   *   array(
   *     'documentation'  => array('path' => 'docs', '404' => 'index'),
   *     'index'          => array(
   *       'path'         => 'index',
   *       'formatter'    => 'class MainController->index'
   *     ),
   *    'license'         => array(
   *       'path'         => 'license',
   *       'cache'        => true
   *    )
   *  )
   *
   * The above will make local directory docs/ reside under documentation/
   * URL, make index.html be formatted by calling MainController::index()
   * and make the license page be cached without expiration limit.
   *
   * Additionally, TextPub can be made serving all requests starting with
   * "textpub/" - in this case it sufficies to set 'handles' in bundles.php
   * and remove 'paths' option from this file.
   *
   +--------------------------------------------------------------------------
   | The 'path' Option
   +--------------------------------------------------------------------------
   *
   * If  it's absolute the following variables are replaced in the resulting path:
   *   - :bundle    - TextPub root (e.g. /home/mysite/bundles/textpub/
   *   - :lang      - request language (e.g. "en")
   *   - :page      - requested page without surrounding slashes (e.g. "path/page")
   *
   * If it's relative the following locations are looked up in order:
   *   - TEXTPUB_ROOT/languages/REQ_LANG/REQ_PAGE
   *   - TEXTPUB_ROOT/storage/REQ_LANG/REQ_PAGE
   *   - TEXTPUB_ROOT/storage/REQ_PAGE
   *   - TEXTPUB_ROOT/public/REQ_LANG/REQ_PAGE
   *   - TEXTPUB_ROOT/public/REQ_PAGE
   *
   * If no page file could be found a 404 response is returned (see '404' option).
   */

  // this is commented out by default so that you can just specify 'handles' by itself.
  //'paths' => array('docs', 'index', 'license'),
);