<?php

namespace OAT\Library\DBALSpanner\Tests\Unit\SessionPool;

use Google\Cloud\Spanner\Session\CacheSessionPool;
use OAT\Library\DBALSpanner\SessionPool\SessionPoolFactory;
use PHPUnit\Framework\TestCase;

class SessionPoolFactoryTest extends TestCase
{
    /** @var SessionPoolFactory */
    private $factory;

    public function setUp(): void
    {
        $this->factory = new SessionPoolFactory();
    }

    public function testCreateDefaultCacheSessionPool(): void
    {
        $this->assertInstanceOf(CacheSessionPool::class, $this->factory->create());
    }
}
