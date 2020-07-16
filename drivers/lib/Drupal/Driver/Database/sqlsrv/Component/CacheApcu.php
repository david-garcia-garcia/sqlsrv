<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

/**
 * Apcu implementation for the in-memory fast
 * cache. Use this for very frequently used cache items.
 */
class CacheApcu implements CacheInterface
{
    private $prefix = null;

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
    private $serializer = null;

    public function __construct($prefix)
    {
        $this->prefix = $prefix;

        // Try to use a serializer...
        if (function_exists('igbinary_serialize')) {
            $this->serializer = new SerializerIgbinary();
        } else {
            $this->serializer = new SerializerPhp();
        }
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
        apcu_store($this->prefix . ':' . $cid, $cache);
    }

    /**
     * {@inheritdoc}
     */
    public function Get($cid)
    {
        if (isset($this->data[$cid])) {
            return $this->data[$cid];
        }
        $success = false;
        $result = apcu_fetch($this->prefix . ':' . $cid, $success);
        if (!$success) {
            return false;
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
    public function Clear($cid)
    {
        apcu_delete($this->prefix . ':' . $cid);
        unset($this->data[$cid]);
    }
}
