<?php

if (Bundle::option('textpub', 'auto')) {
  $paths = TextPub::option('paths') ?: Bundle::option('textpub', 'handles');
  if (!$paths) {
    throw new Exception("Text Publisher (textpub) requires 'paths' or/and".
                        " 'handles' options but none are set.");
  }

  TextPub::register(''.Router::$bundle, $paths);
}
