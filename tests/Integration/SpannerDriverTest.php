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
 * Copyright (c) 2019-2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\DBALSpanner\Tests\Integration;

use Doctrine\DBAL\Connection;
use Google\Cloud\Spanner\Session\SessionPoolInterface;
use Google\Cloud\Spanner\SpannerClient;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\Tests\_helpers\Configuration;
use OAT\Library\DBALSpanner\Tests\_helpers\ConfigurationTrait;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class SpannerDriverTest extends TestCase
{
    use NoPrivacyTrait;
    use ConfigurationTrait;

    /** @var SpannerDriver */
    private $subject;

    /** @var SpannerClientFactory|MockObject */
    private $spannerClientFactory;

    /** @var SessionPoolInterface|MockObject */
    private $sessionPool;

    public function setUp(): void
    {
        $this->subject = new SpannerDriver();
    }

    public function testConnectUsingDriverOptions(): void
    {
        $authCache = $this->createMock(CacheItemPoolInterface::class);
        $sessionPool = $this->createMock(SessionPoolInterface::class);
        $keyPath = $this->getConfiguration(Configuration::CONFIG_KEY_FILE_PATH);
        $clientConfiguration = [
            'a' => '1',
            'b' => '2',
        ];

        $connection = $this->subject->connect(
            [
                'instance' => 'toto',
                'dbname' => 'titi'
            ],
            null,
            null,
            [
                SpannerDriver::DRIVER_OPTION_AUTH_POOL => $authCache,
                SpannerDriver::DRIVER_OPTION_SESSION_POOL => $sessionPool,
                SpannerDriver::DRIVER_OPTION_CREDENTIALS_FILE_PATH => $keyPath,
                SpannerDriver::DRIVER_OPTION_CLIENT_CONFIGURATION => $clientConfiguration,
            ]
        );

        /** @var SpannerClientFactory $clientFactory */
        $clientFactory = $this->getPrivateProperty($this->subject, 'spannerClientFactory');

        $this->assertInstanceOf(SpannerConnection::class, $connection);
        $this->assertSame('titi', $this->subject->getDatabase($this->createMock(Connection::class)));
        $this->assertSame('toto', $this->getPrivateProperty($this->subject, 'instanceName'));
        $this->assertSame($sessionPool, $this->getPrivateProperty($this->subject, 'sessionPool'));
        $this->assertSame($this->subject, $this->getPrivateProperty($connection, 'driver'));
        $this->assertSame($authCache, $this->getPrivateProperty($clientFactory, 'authCache'));
        $this->assertSame($keyPath, $this->getPrivateProperty($clientFactory, 'keyFilePath'));
        $this->assertSame($clientConfiguration, $this->getPrivateProperty($clientFactory, 'configuration'));
    }
}
