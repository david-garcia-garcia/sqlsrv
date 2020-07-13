<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

/**
 * Wincache implementation for the in-memory fast
 * cache. Use this for very frequently used cache items.
 */
class CacheWincache implements CacheInterface
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
        wincache_ucache_set($this->prefix . ':' . $cid, $cache);
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
        $result = wincache_ucache_get($this->prefix . ':' . $cid, $success);
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
        if (empty($cid)) {
            $info = wincache_ucache_info();
            foreach ($info['ucache_entries'] as $entry) {
                $key = $entry['key_name'];
                if (strpos($key, $this->prefix . ':') === 0) {
                    wincache_ucache_delete($key);
                    unset($this->data[$key]);
                }
            }
        } else {
            wincache_ucache_delete($this->prefix . ':' . $cid);
            unset($this->data[$cid]);
        }
    }
}
