<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

interface CacheFactoryInterface
{

  /**
   * Get a cache backend for a specific binary.
   *
   * @param  string $bin
   *
   * @return CacheInterface
   */
    public function get($bin);
}
