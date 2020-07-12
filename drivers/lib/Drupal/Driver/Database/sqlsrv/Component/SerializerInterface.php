<?php

namespace Drupal\Driver\Database\sqlsrv\Component;

interface SerializerInterface {

  /**
   * Serialize data.
   *
   * @param mixed $data
   *
   * @return string
   */
  function serialize($data);

  /**
   * Unserialize data.
   *
   * @param string $data
   *
   * @return mixed
   */
  function unserialize($data);
}
