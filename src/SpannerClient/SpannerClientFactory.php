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

namespace OAT\Library\DBALSpanner\SpannerClient;

use Google\Auth\Cache\SysVCacheItemPool;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Lock\SemaphoreLock;
use Google\Cloud\Spanner\Session\CacheSessionPool;
use Google\Cloud\Spanner\SpannerClient;

class SpannerClientFactory
{
    private const KEY_FILE_ENV_VARIABLE = 'GOOGLE_APPLICATION_CREDENTIALS';
    private const SESSIONS_MIN = 1;
    private const SESSIONS_MAX = 100;

    /**
     * Creates a Google Spanner client from env configuration.
     *
     * @return SpannerClient
     * @throws GoogleException When ext/grpc is missing.
     */
    public function create()
    {
        $authCache = new SysVCacheItemPool();
        $keyFileName = $_ENV[self::KEY_FILE_ENV_VARIABLE] ?? '';
        if ($keyFileName === '') {
            new GoogleException(
                sprintf('Missing path to Google credentials key file (should be set as an environment variable "%s").'),
                self::KEY_FILE_ENV_VARIABLE
            );
        }
        
        $keyFile = json_decode(file_get_contents($keyFileName), true);

        return new SpannerClient(['keyFile' => $keyFile, 'authCache' => $authCache]);
    }

    /**
     * Creates a session pool to allow multiple sessions to share the auth cache.
     *
     * @return CacheSessionPool
     * @throws \Exception
     */
    public function createCacheSessionPool()
    {
        // Use a different project identifier for ftok than the default.
        $sessionCache = new SysVCacheItemPool(['proj' => 'B']);

        // Creates multiple sessions.
        $sessionPool = new CacheSessionPool(
            $sessionCache,
            [
                'lock' => new SemaphoreLock(65535),
                'minSessions' => self::SESSIONS_MIN,
                'maxSessions' => self::SESSIONS_MAX,
            ]
        );

        return $sessionPool;
    }
}
