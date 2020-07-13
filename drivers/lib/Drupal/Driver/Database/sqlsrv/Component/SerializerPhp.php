<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

class SerializerPhp implements SerializerInterface
{

  /**
   * {@inheritdoc}
  */
    public function serialize($value)
    {
        return serialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($value)
    {
        return unserialize($value);
    }
}
