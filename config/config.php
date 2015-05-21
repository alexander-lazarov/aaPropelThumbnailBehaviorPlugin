<?php

sfPropelBehavior::registerMethods('thumbnail', array(
  array('aaPropelThumbnailBehavior', 'thumbnail'),
  array('aaPropelThumbnailBehavior', 'generateThumbnails')
));

sfPropelBehavior::registerHooks( 'thumbnail', array( 
  ':save:pre' => array('aaPropelThumbnailBehavior', 'preSave')
));
