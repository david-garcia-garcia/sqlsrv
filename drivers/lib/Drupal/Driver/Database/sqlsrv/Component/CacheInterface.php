<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

interface CacheInterface
{

  /**
   * Set a cache item.
   *
   * @param string $cid
   * @param mixed $data
   */
    public function Set($cid, $data);

    /**
     * Get a cache item.
     *
     * @param mixed $cid
     */
    public function Get($cid);

    /**
     * Clear a cache item.
     *
     * @param string $cid
     */
    public function Clear($cid);
}
