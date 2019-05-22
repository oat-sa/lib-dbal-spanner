<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner\Tests\Integration;

use Psr\Log\AbstractLogger;

/**
 * Simpler implementation to see what's going when setting up a Spanner instance.
 * No assertion is made so no need for a TestLogger.
 */
class EchoLogger extends AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        echo $message, "\n";
    }
}
