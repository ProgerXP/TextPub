<?php
return array(
  'textpub' => array(
    'handles' => 'text',

    /*
     * Autoloading is required unless you include 'handles' here and omit
     * 'paths' in config/general.php. This will make TextPub serve all pages
     * requested under the 'handles' URL and thus it will be started by Laravel
     * only when necessary for those requests.
     */
    //'auto' => true,

    // standard autoloader class mapping.
    'autoloads' => array(
      'map' => array('TextPub' => '(:bundle)/api.php')
    )
  )
);