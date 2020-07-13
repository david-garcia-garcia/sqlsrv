<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

interface SerializerInterface
{

  /**
   * Serialize data.
   *
   * @param mixed $data
   *
   * @return string
   */
    public function serialize($data);

    /**
     * Unserialize data.
     *
     * @param string $data
     *
     * @return mixed
     */
    public function unserialize($data);
}
