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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\DBALSpanner\SessionPool;

use Google\Auth\Cache\SysVCacheItemPool;
use Google\Cloud\Core\Lock\SemaphoreLock;
use Google\Cloud\Spanner\Session\CacheSessionPool;
use Google\Cloud\Spanner\Session\SessionPoolInterface;

class SessionPoolFactory
{
    public const OPTION_MIN_SESSIONS = 'minSessions';
    public const OPTION_MAX_SESSIONS = 'maxSessions';
    public const OPTION_PROJ = 'proj';

    private const SESSIONS_MIN = 1;
    private const SESSIONS_MAX = 100;

    public function create(array $params): SessionPoolInterface
    {
        return new CacheSessionPool(
            new SysVCacheItemPool(
                [
                    'proj' => $params[self::OPTION_PROJ] ?? 'A',
                ]
            ),
            [
                'lock' => new SemaphoreLock(65535),
                'minSessions' => $params[self::OPTION_MIN_SESSIONS] ?? self::SESSIONS_MIN,
                'maxSessions' => $params[self::OPTION_MAX_SESSIONS] ?? self::SESSIONS_MAX,
            ]
        );
    }
}
