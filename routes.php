<?php

$paths = TextPub::option('paths') ?: Bundle::option($bundle, 'handles');
if (!$paths) {
  throw new Exception("Text Publisher ($bundle) requires 'paths' or/and".
                      " 'handles' options but none are set.");
}

TextPub::register('', $paths);
