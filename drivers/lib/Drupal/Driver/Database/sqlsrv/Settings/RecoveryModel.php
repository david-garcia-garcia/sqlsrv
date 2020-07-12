<?php

namespace Drupal\Driver\Database\sqlsrv\Settings;

use \Drupal\Driver\Database\sqlsrv\Component\Enum;

class RecoveryModel extends Enum {
  const Full = 'FULL';
  const BulkLogged = 'BULK_LOGGED';
  const Simple = 'SIMPLE';
}
