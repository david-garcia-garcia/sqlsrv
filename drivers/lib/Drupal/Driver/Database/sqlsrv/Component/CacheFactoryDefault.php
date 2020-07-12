<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

class CacheFactoryDefault implements CacheFactoryInterface {

  /**
   * Unique prefix for this site/database
   *
   * @param string $prefix
   *   Unique prefix for this site/database
   */
  public function __construct($prefix) {
    $this->prefix = $prefix;
  }

  /**
   * Unique prefix for this database
   *
   * @var string
   */
  protected $prefix;

  /**
   * List of already loaded cache binaries.
   *
   * @var CacheInterface[]
   */
  protected $binaries = [];

  /**
   * {@inhertidoc}
   */
  public function get($bin) {
    $name = $this->prefix . ':' . $bin;
    if (!isset($this->binaries[$name])) {
      if (extension_loaded('wincache')) {
        $this->binaries[$name] = new CacheWincache($name);
      }
      elseif (function_exists("apcu_get")) {
        $this->binaries[$name] = new CacheApcu($name);
      }
      else {
        $this->binaries[$name] = new CacheStub($name);
      }
    }
    return $this->binaries[$name];
  }
}
