<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

class SerializerIgbinary implements SerializerInterface
{

  /**
   * {@inheritdoc}
  */
    public function serialize($value)
    {
        return igbinary_serialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($value)
    {
        return igbinary_unserialize($value);
    }
}
