# Text Publisher

**TextPub** is a flexible text serving interface. It works with **various formatters** which can be customized per page file extension; it supports **caching** and **custom 404 pages**.

It can also optionally reside under its own URL (bundle prefix set by **handles**) or under site's root serving specific URLs only.

**TextPub** is used as a backend to serve [Laravel.ru](http://laravel.ru) documentation, articles and several standalone pages.

## Installation
Put this into your **application/bundles.php**:
<pre>
'textpub' => array(
  'handles' => 'text',

  'autoloads' => array(
    'map' => array('TextPub' => '(:bundle)/api.php')
  )
)
</pre>

> Depending on your configuration (see below) you can also set **auto** and remove **handles**. However, the autoloader option should always be present.

> Default configuration allows serving of **txt**, **html** and **php** pages out of the box if they are located under _TextPub_'s `storage/` or `public`/ (there are more actually, see the *path* option below).

## Configuration
All configuration is done in **textpub/config/general.php**; this file contains extensive descriptions so please refer there for the latest information.

A brief overview of what you can customize follows.

- **ext** sets which file extensions are served and how they are formatted
- **formatter** specifies default formatter for those not listed in **ext**
- **layout** sets the name of the view used to output formatted pages; by default it refers to the bare but complete XHTML view coming with _TextPub_ (`views/text.php`) - it can serve as an example and show which variables are always available
- **index** sets the name of page to be served when a text directory is requested (without an explicit page name)
- **cache** sets which page formats are cached and how
- **debug** turns on logging of some debug info
- **as** enables registration of the named routes for configured text pages
- **single** tells that a specific page is a single page rather than a directory (group of pages)
- **404** sets the page used to return 404 responses instead of Laravel default 404 handler
- **paths** lists actually served pages - can be absent if **handles** is specified in the bindle configuration (see the _Installation_ section); if it's present it's an array of all options listed above overriding global options of the same name

### Paths
Each **paths** item can contain **path** option to specify where page file(s) is located physically.

If it's an **absolute** path `:bundle`, `:lang` and `:page` are replaced with the path to _TextPub_ root, current request language and requested page correspondingly

If it's **relative** _TextPub_ searches the page file in the following locations, returning the first it finds:

1. :bundle/languages/:lang/:page
1. :bundle/storage/:lang/:page
1. :bundle/storage/:page
1. :bundle/public/:lang/:page
1. :bundle/public/:page
