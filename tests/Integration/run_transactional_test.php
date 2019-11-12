<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA;
 */

use OAT\Library\DBALSpanner\Tests\Integration\TransactionalTest;

require_once __DIR__ . '/../../vendor/autoload.php';

$test = new TransactionalTest();

if (count($argv) === 2 && $argv['1'] === 'run') {
    $test->run('run process ' . posix_getpid());
    exit;
}

if (count($argv) === 2 && $argv['1'] === 'transactional') {
    $test->transactional('transactional process ' . posix_getpid());
    exit;
}

$test->prepare();
$logFile = __DIR__ . '/transactional.log';

// Makes two concurrent operations.
echo "process 1\n";
exec('php ' . __DIR__ . '/run_transactional_test.php run >>' . $logFile . ' 2>&1 &');
echo "process 2\n";
exec('php ' . __DIR__ . '/run_transactional_test.php run >>' . $logFile . ' 2>&1 &');

sleep(3);

$test->checkFinalRun();

// Makes two concurrent operations.
echo "process 1\n";
exec('php ' . __DIR__ . '/run_transactional_test.php transactional >>' . $logFile . ' 2>&1 &');
echo "process 2\n";
exec('php ' . __DIR__ . '/run_transactional_test.php transactional >>' . $logFile . ' 2>&1 &');

sleep(3);

$test->checkFinalTransactional();



$test->finish();

if (count($test->failures)) {
    echo "\n";
    foreach ($test->failures as $failure) {
        echo $failure;
    }
} else {
    echo "\nAll tests passed.\n";
}

echo file_get_contents($logFile);
