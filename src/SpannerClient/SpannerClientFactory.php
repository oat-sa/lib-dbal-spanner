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
use Google\Cloud\Spanner\Session\SessionPoolInterface;
use Google\Cloud\Spanner\SpannerClient;
use Psr\Cache\CacheItemPoolInterface;

class SpannerClientFactory
{
    private const KEY_FILE_ENV_VARIABLE = 'GOOGLE_APPLICATION_CREDENTIALS';
    private const DEFAULT_CREDENTIALS_FILE = '/var/google/key/key.json';
    private const SESSIONS_MIN = 1;
    private const SESSIONS_MAX = 100;

    /** @var CacheItemPoolInterface|null */
    private $authCache;

    /** @var array */
    private $configuration;

    /** @var string|null */
    private $keyFilePath;

    public function __construct(
        CacheItemPoolInterface $authCache = null,
        array $configuration = null,
        string $keyFilePath = null
    ) {
        $this->authCache = $authCache ?? new SysVCacheItemPool();
        $this->configuration = $configuration ?? [];
        $this->keyFilePath = $keyFilePath === null
            ? ($_ENV[self::KEY_FILE_ENV_VARIABLE] ?? self::DEFAULT_CREDENTIALS_FILE)
            : $keyFilePath;
    }

    /**
     * @throws GoogleException
     */
    public function create(): SpannerClient
    {
        return new SpannerClient(
            array_merge(
                [
                    'keyFile' => $this->getKeyFileParsedContent(),
                    'authCache' => $this->authCache
                ],
                $this->configuration
            )
        );
    }

    /**
     * @TODO We must remove this method in the next major version
     *
     * @deprecated Please DO NOT use this method. The client of this library should define it if needed
     */
    public function createCacheSessionPool(): SessionPoolInterface
    {
        return new CacheSessionPool(
            new SysVCacheItemPool(['proj' => 'B']),
            [
                'lock' => new SemaphoreLock(65535),
                'minSessions' => self::SESSIONS_MIN,
                'maxSessions' => self::SESSIONS_MAX,
            ]
        );
    }

    /**
     * @throws GoogleException
     */
    private function getKeyFileParsedContent(): array
    {
        if (empty($this->keyFilePath) || !is_readable($this->keyFilePath)) {
            throw new GoogleException(
                sprintf(
                    'Missing path to Google credentials key file (should be set as an environment variable "%s").',
                    self::KEY_FILE_ENV_VARIABLE
                )
            );
        }

        return json_decode(file_get_contents($this->keyFilePath), true);
    }
}
