<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

/**
 * Stub implementation for the cache backend.
 */
class CacheStub implements CacheInterface
{
    private $prefix = null;

    /**
     * This cache stores everything in-memory during the
     * lifetime of this request.
     *
     * @var array
     */
    private $data = [];

    public function __construct($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function Set($cid, $data)
    {
        $cache = new \stdClass();
        $cache->data = $data;
        $cache->serialized = false;
        $cache->timestamp = time();
        $this->data[$cid] = clone $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function Get($cid)
    {
        if (isset($this->data[$cid])) {
            return $this->data[$cid];
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function Clear($cid)
    {
        unset($this->data[$cid]);
    }
}
