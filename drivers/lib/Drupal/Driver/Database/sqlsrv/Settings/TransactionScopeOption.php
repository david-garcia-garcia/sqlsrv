<?php

namespace Drupal\Driver\Database\sqlsrv\Settings;

use Drupal\Driver\Database\sqlsrv\Component\Enum;

class TransactionScopeOption extends Enum {
  const RequiresNew = 'RequiresNew';
  const Supress = 'Supress';
  const Required = 'Required';
}
