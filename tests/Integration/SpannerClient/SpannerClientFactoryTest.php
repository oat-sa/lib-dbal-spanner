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

namespace OAT\Library\DBALSpanner\Tests\Integration\SpannerClient;

use Google\Cloud\Spanner\SpannerClient;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;
use OAT\Library\DBALSpanner\Tests\_helpers\Configuration;
use OAT\Library\DBALSpanner\Tests\_helpers\ConfigurationTrait;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\TestCase;

class SpannerClientFactoryTest extends TestCase
{
    use ConfigurationTrait;

    public function testGetInstanceWithoutGrpcInstalledThrowsException()
    {
        $this->mockFunction(
            'OAT\Library\DBALSpanner\SpannerClient',
            "getenv",
            $this->getConfiguration(Configuration::CONFIG_KEY_FILE_PATH)
        );

        $subject = new SpannerClientFactory();
        $spannerClient = $subject->create();

        $this->assertInstanceOf(SpannerClient::class, $spannerClient);
    }

    /**
     * Mocks general scope's time function.
     * If $assertParameters is provided, asserts that the parameters received are matching.
     *
     * @param string     $namespace
     * @param string     $functionName
     * @param mixed      $value
     * @param array|null $assertParameters
     *
     * @return Mock
     * @throws \phpmock\MockEnabledException
     */
    protected function mockFunction($namespace, $functionName, $value, $assertParameters = null)
    {
        $builder = new MockBuilder();
        $builder->setNamespace($namespace)
            ->setName($functionName)
            ->setFunction(
                function () use ($value, $assertParameters) {
                    if (is_array($assertParameters)) {
                        $this->assertEquals($assertParameters, func_get_args());
                    }
                    return $value;
                }
            );

        $mock = $builder->build();
        $mock->enable();

        return $mock;
    }
}
