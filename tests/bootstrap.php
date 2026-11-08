<?php

define('_PS_VERSION_', '1.7.6.0');
define('_DB_PREFIX_', 'ps_');
define('_PS_USE_SQL_SLAVE_', true);

$repoRoot = dirname(__DIR__);

require_once $repoRoot . '/tests/PrestashopDoubles.php';

Context::resetContext();
$db = Db::getInstance(true);
$db->ensureSchema();

require_once $repoRoot . '/statscatalog.php';
require_once $repoRoot . '/upgrade/upgrade-2.0.2.php';

require_once $repoRoot . '/tests/StatsCatalogTestCase.php';
