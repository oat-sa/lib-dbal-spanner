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

namespace OAT\Library\DBALSpanner\Tests\Unit\SessionPool;

use Google\Cloud\Spanner\Session\CacheSessionPool;
use OAT\Library\DBALSpanner\SessionPool\SessionPoolFactory;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class SessionPoolFactoryTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var SessionPoolFactory */
    private $factory;

    /** @var MockObject|RedisAdapter */
    private $redisSessionPool;

    public function setUp(): void
    {
        $this->factory = new SessionPoolFactory();
        $this->redisSessionPool = $this->createMock(RedisAdapter::class);
    }

    public function testCreateDefaultCacheSessionPool(): void
    {
        $cache = $this->factory->create(
            [
                'minSessions' => 5,
                'maxSessions' => 6,
            ]
        );

        $this->assertInstanceOf(CacheSessionPool::class, $cache);
        $this->assertSame(5, $this->getPrivateProperty($cache, 'config')['minSessions']);
        $this->assertSame(6, $this->getPrivateProperty($cache, 'config')['maxSessions']);
    }

    public function testRedisCacheSessionPool(): void
    {
        $factory = new SessionPoolFactory($this->redisSessionPool);
        $cache = $factory->create();

        $this->assertInstanceOf(RedisAdapter::class, $this->getPrivateProperty($cache, 'cacheItemPool'));
    }
}
