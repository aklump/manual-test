<?php

/**
 * @file
 * This file bootstraps the app.
 */

use AKlump\LoftLib\Bash\Bash;
use AKlump\ManualTest\ManualTest;

// Check for manual_test installed as composer global package.
if (file_exists(__DIR__ . '/../../../autoload.php')) {
  require_once __DIR__ . '/../../../autoload.php';
}
else {
  require_once ROOT . '/vendor/autoload.php';
}

$cli = new Bash($argv);

//// Validate flags.
//if (($diff = array_diff($cli->getFlags(), ['v', 'i']))) {
//  throw new \InvalidArgumentException("Unknown flag: " . reset($diff));
//}
//
//// Validate parameters.
//if (($diff = array_diff_key($cli->getParams(), array_flip([
//
//  // Enter valid parameters.
//  'precision',
//  'nearby',
//  'width',
//  'variance',
//])))) {
//  throw new \InvalidArgumentException("Unknown parameter: " . key($diff));
//}
//
//// Display the version.
//if ($cli->hasFlag('v')) {
//  $data = FilePath::create(ROOT . '/composer.json')->load()->getJson();
//  $credits = sprintf("Manual Test by In the Loft Studios ~ Version %s", $data->version);
//  echo Color::wrap('cyan', $credits) . PHP_EOL;
//  exit(0);
//}


