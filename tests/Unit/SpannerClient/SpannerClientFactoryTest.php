<?php

namespace OAT\Library\DBALSpanner\Tests\Unit\SpannerClient;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Spanner\Session\SessionPoolInterface;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;
use PHPUnit\Framework\TestCase;

class SpannerClientFactoryTest extends TestCase
{
    public function testCreateWithWrongCredentialsPathWillThrowException(): void
    {
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage(
            'Missing path to Google credentials key file (should be set as an environment variable "GOOGLE_APPLICATION_CREDENTIALS").'
        );

        $subject = new SpannerClientFactory(null, null, 'invalid');
        $subject->create();
    }

    /**
     * @deprecated Test will be removed as soon as the deprecated method is removed
     */
    public function testCreateCacheSessionPool(): void
    {
        $this->assertInstanceOf(SessionPoolInterface::class, (new SpannerClientFactory())->createCacheSessionPool());
    }
}
