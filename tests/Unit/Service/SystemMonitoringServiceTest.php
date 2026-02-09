<?php

namespace App\Tests\Unit\Service;

use App\Service\SystemMonitoringService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class SystemMonitoringServiceTest extends TestCase
{
    private SystemMonitoringService $service;

    protected function setUp(): void
    {
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);

        $this->service = new SystemMonitoringService(
            '/fake/proc',
            '/fake/os-release',
            $cacheMock
        );
    }

    public function testFormatBytesWithZero(): void
    {
        $result = $this->service->formatBytes(0);
        $this->assertEquals('0 B', $result);
    }

    public function testFormatBytesWithKilobytes(): void
    {
        $result = $this->service->formatBytes(1024);
        $this->assertEquals('1.00 KB', $result);
    }

    public function testFormatBytesWithMegabytes(): void
    {
        $result = $this->service->formatBytes(1048576);
        $this->assertEquals('1.00 MB', $result);
    }

    public function testFormatBytesWithGigabytes(): void
    {
        $result = $this->service->formatBytes(1073741824);
        $this->assertEquals('1.00 GB', $result);
    }

    public function testFormatBytesWithTerabytes(): void
    {
        $result = $this->service->formatBytes(1099511627776);
        $this->assertEquals('1.00 TB', $result);
    }

    public function testFormatBytesWith5MB(): void
    {
        $input = 5242880;
        $result = $this->service->formatBytes($input);
        $this->assertEquals('5.00 MB', $result);
    }
}