<?php

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
