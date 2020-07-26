SQL Server Driver for Drupal
=====================

### For Windows or Linux

This contrib module allows the Drupal CMS to connect to Microsoft SQL Server databases.

Setup
-----

Use [composer](http://getcomposer.org) to install the module:

```bash
$ php composer require drupal/sqlsrv:8.x-2.x-dev
```

The `drivers/` directory needs to be copied to webroot of your drupal installation, or you can symlink directly the directories by running in your "webs" directory:

```bash
mklink /d drivers modules\contrib\sqlsrv\drivers
```

