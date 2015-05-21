<?php

class aaPropelThumbnailBehavior {

  // TODO CHECK when behavior object is created
  public function preSetFilename( BaseObject $object ) {

  }

  const DEFAULT_EXTENSION = 'jpg';

  /**
   * @var boolean
   */
  private $is_configured = false;

  /**
   * Object variable
   *
   * @var BaseObject
   */
  protected $object;

  /**
   * Object class
   *
   * @var string
   */
  protected $class;

  /**
   * Name of the peer class of the object
   *
   * @var string
   */
  protected $peerClass;


  /**
   * Array holding all options for current behavior/object
   *
   * @var array
   */
  protected $options = array(
    'quality' => 85,
    'mime_type' => 'image/jpeg',
    'extension' => 'jpg',
    'type' => 'fit'
  );


  protected function configure( BaseObject $object ) {
    $this->object = $object;

    $this->class = get_class( $this->object );
    $this->peerClass = get_class( $this->object->getPeer() );

    $prefix = 'propel_behavior_thumbnail_'.$this->class.'_';

    $config_array = array();
    $config_array['thumbs'] = sfConfig::get( $prefix.'thumbs' );
    $config_array['quality'] = sfConfig::get( $prefix.'quality' );
    $config_array['mime_type'] = sfConfig::get( $prefix.'mime_type' );
    $config_array['extension'] = sfConfig::get( $prefix.'extensn' );
    $config_array['column'] = sfConfig::get( $prefix.'column' );
    $config_array['path'] = sfConfig::get( $prefix.'path' );
    $config_array['web_path'] = '../uploads/'.sfConfig::get( $prefix.'path' ).'/';

    // merge the two arrays
    foreach( $config_array as $key=>$value ) {
      // do not overwrite if the value is empty
      if( NULL !== $value ) {
        $this->options[ $key ] = $value;
      }
    }

    if( !$this->options['column'] ) {
      throw new Exception( 'Option "column" not set' );
    }

    if( !$this->options['path'] ) {
      throw new Exception( 'Option "path" not set' );
    }
    $this->options['path'] = sfConfig::get( 'sf_upload_dir' ).$this->options['path'];

    // make sure we have DIRECTORY_SEPARATOR in the end of the var
    if( mb_substr( $this->options['path'], -1, 1 ) != DIRECTORY_SEPARATOR ) {
      $this->options['path'] .= DIRECTORY_SEPARATOR;
    }

    if( !is_writable( $this->options['path'] ) ) {
      throw new Exception( '"path" is not writable' );
    }
    if( !is_array( $this->options['thumbs'] ) ) {
      throw new Exception( 'Option "thumbs" not set properly' );
    }

    return;
  }

  // TODO
  protected function filename( $thumb_label = '' ) {
    $filename = $this->getColumnValue();
    if( $thumb_label ) {
      $filename = self::changeExtension( $filename, $thumb_label.'.'.$this->options['extension'] );
    }

    return $filename;
  }

  static $supportedTypes = array(
    'scale',
    'fit',
    'inflate',
    'deflate',
    'left',
    'right',
    'top',
    'bottom',
    'center'
  );

  /**
   * Changes the extension of filename, for example
   * @example changeExtension( 'foo.jpeg', 'jpg' ) // returns foo.jpg
   * @example changeExtension( 'foo.jpeg', '' ) // returns foo
   * @example changeExtension( 'foo', 'jpg' ) // returns foo.jpg
   *
   * @param string $filename
   * @param string $newExtension
   */
  protected static function changeExtension( $filename, $newExtension ) {
    $position = mb_strrpos( $filename, '.');

    if( $position === FALSE) {
      return $filename.'.'.$newExtension;
    }

    $basename = mb_substr( $filename, 0, $position);
    if( $newExtension ) {
      $newExtension =  '.' . $newExtension;
    }

    return $basename.$newExtension;
  }


  /**
   * Gets the field name to be used for storing the filename
   * @param $type BasePeer::TYPE_COLNAME or BasePeer::TYPE_FIELDNAME or BasePeer::TYPE_PHPNAME @see
   * BasePeer for more info
   * @return string
   */
  protected function getColumn( $type = BasePeer::TYPE_COLNAME ) {
    return call_user_func(
      array($this->peerClass, 'translateFieldName'),
      $this->options['column'],
      BasePeer::TYPE_COLNAME,
      $type
    );
  }

  /**
   * Gets the value of the field which is used for storing the filename
   *
   * @return string
   */
  protected function getColumnValue( ) {
    return call_user_func( array( $this->object, 'get'.$this->getColumn( BasePeer::TYPE_PHPNAME ) ) );
  }

  /**
   * Tries to guess the mime type of the file from the extension.
   * Usually sfValidatorFile changes extension so this should work in most cases
   * @result string mime type
   */
  protected function guessMimeType(  ) {

    $filename = $this->filename();

    $position = mb_strrpos( $filename, '.');

    $extension = mb_substr( $filename, $position+1 );

    switch( $extension ) {
      case 'png':
        return 'image/png'; break;
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg'; break;
      case 'gif':
        return 'image/gif'; break;
    }

    return '';
  }

  public function thumbnail( BaseObject $object, $label = null ) {
    $this->configure( $object );

    return $this->options['web_path'].$this->filename( $label );
  }

  public function generateThumbnails( BaseObject $object ) {
      $this->configure( $object );
      $filename = $this->filename();

      // generate the thumbnails
      try {
        foreach( $this->options['thumbs'] as $thumb_label => $thumb ) {
          $w = $thumb['w'];
          $h = $thumb['h'];
          $type = array_key_exists( 'thumbnailing_type', $thumb )?$thumb['thumbnailing_type']:'';

          if( $type && !in_array( $type, self::$supportedTypes ) ) {
            throw new Exception( sprintf( 'Thumbnailing type "%s" not supported. Please check your config.', $type ) );
          }

          $newFilename = $this->filename( $thumb_label );

          $sf_image = new sfImage( $this->options['path'] . $filename, $this->guessMimeType() );
          $sf_image->setQuality( $this->options['quality'] );
          $sf_image->thumbnail( $w, $h, $type );
          $sf_image->saveAs( $this->options['path'].$newFilename );

        }
      }
      catch( Exception $e ) {
        throw new Exception( "Error occured when generating thumbnail: ".$e );
      }

  }

  public function preSave( BaseObject $object, PropelPDO $con = null ) {
    $this->configure( $object );
    $filename = $this->getColumnValue( );

    if( $object->isColumnModified( $this->getColumn() ) && $filename ) {
      $this->generateThumbnails( $object );
    }

  }


  public static function preDelete( BaseObject $object, PropelPDO $con = null ) {
    $this->configure( $object );
    // delete images
    @ unlink( $this->options['path'].$this->filename( ) );
    foreach( $this->options['thumbs'] as $key=>$value )  {
      @ unlink( $this->options['path'].$this->filename( $key ) );
    }
  }

}
