<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

/**
 * Stub implementation for the cache backend.
 */
class CacheStub implements CacheInterface {

  private $prefix = NULL;

  /**
   * This cache stores everything in-memory during the
   * lifetime of this request.
   *
   * @var array
   */
  private $data = [];

  public function __construct($prefix) {
    $this->prefix = $prefix;
  }

  /**
   * {@inheritdoc}
   */
  function Set($cid, $data) {
    $cache = new \stdClass();
    $cache->data = $data;
    $cache->serialized = FALSE;
    $cache->timestamp = time();
    $this->data[$cid] = clone $cache;
  }

  /**
   * {@inheritdoc}
   */
  function Get($cid) {
    if (isset($this->data[$cid])) {
      return $this->data[$cid];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  function Clear($cid) {
    unset($this->data[$cid]);
  }
}
