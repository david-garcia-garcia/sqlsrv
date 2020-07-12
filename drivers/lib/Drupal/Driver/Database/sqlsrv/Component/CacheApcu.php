<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

/**
 * Apcu implementation for the in-memory fast
 * cache. Use this for very frequently used cache items.
 */
class CacheApcu implements CacheInterface {

  private $prefix = NULL;

  /**
   * This cache stores everything in-memory during the
   * lifetime of this request.
   *
   * @var array
   */
  private $data = [];

  /**
   * Serializer to use.
   *
   * @var SerializerInterface
   */
  private $serializer = NULL;

  public function __construct($prefix) {
    $this->prefix = $prefix;

    // Try to use a serializer...
    if (function_exists('igbinary_serialize')) {
      $this->serializer = new SerializerIgbinary();
    }
    else {
      $this->serializer = new SerializerPhp();
    }
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
    apcu_store($this->prefix . ':' . $cid, $cache);
  }

  /**
   * {@inheritdoc}
   */
  function Get($cid) {
    if (isset($this->data[$cid])) {
      return $this->data[$cid];
    }
    $success = FALSE;
    $result = apcu_fetch($this->prefix . ':' . $cid, $success);
    if (!$success) {
      return FALSE;
    }
    if (isset($result->serialized) && $result->serialized) {
      $result->data = $this->serializer->unserialize($result->data);
    }
    $this->data[$cid] = $result;
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  function Clear($cid) {
    apcu_delete($this->prefix . ':' . $cid);
    unset($this->data[$cid]);
  }
}
