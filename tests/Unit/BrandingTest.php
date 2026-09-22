<?php

namespace OCA\Appointments\Tests\Unit;

use OCA\Appointments\Backend\BackendUtils;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BrandingTest extends TestCase
{
    /** @var IConfig&MockObject */
    private IConfig $config;
    private BackendUtils $utils;

    protected function setUp(): void
    {
        $this->config = $this->createMock(IConfig::class);
        $this->utils = new BackendUtils(
            $this->config,
            $this->createMock(IDBConnection::class),
            $this->createMock(IURLGenerator::class),
            $this->createMock(IL10N::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testGetBrandsReturnsEmptyArrayWhenNotSet(): void
    {
        $this->config->method('getAppValue')->willReturn('');
        $this->assertSame([], $this->utils->getBrands());
    }

    public function testGetBrandsReturnsEmptyArrayOnInvalidJson(): void
    {
        $this->config->method('getAppValue')->willReturn('not json');
        $this->assertSame([], $this->utils->getBrands());
    }

    public function testGetBrandsReturnsParsedArray(): void
    {
        $brands = [
            ['id' => 'brand_1', 'name' => 'Acme', 'logoUrl' => 'https://example.com/logo.png'],
        ];
        $this->config->method('getAppValue')->willReturn(json_encode($brands));
        $this->assertSame($brands, $this->utils->getBrands());
    }

    public function testGetBrandReturnsNullWhenNotFound(): void
    {
        $this->config->method('getAppValue')->willReturn('[]');
        $this->assertNull($this->utils->getBrand('nonexistent'));
    }

    public function testGetBrandReturnsMatchingBrand(): void
    {
        $brands = [
            ['id' => 'brand_aaa', 'name' => 'Alpha'],
            ['id' => 'brand_123', 'name' => 'Beta', 'logoUrl' => 'https://beta.example.com/logo.png'],
        ];
        $this->config->method('getAppValue')->willReturn(json_encode($brands));
        $result = $this->utils->getBrand('brand_123');
        $this->assertNotNull($result);
        $this->assertSame('Beta', $result['name']);
        $this->assertSame('https://beta.example.com/logo.png', $result['logoUrl']);
    }

    public function testSaveBrandsSerializesCorrectly(): void
    {
        $brands = [
            ['id' => 'brand_x', 'name' => 'X Corp', 'primaryColor' => '#ff0000'],
        ];
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with(
                'appointments',
                BackendUtils::BRANDING_APP_CONFIG_KEY,
                json_encode($brands)
            );
        $this->utils->saveBrands($brands);
    }

    public function testGetBrandReturnsFirstMatchOnly(): void
    {
        $brands = [
            ['id' => 'brand_dup', 'name' => 'First'],
            ['id' => 'brand_dup', 'name' => 'Second'],
        ];
        $this->config->method('getAppValue')->willReturn(json_encode($brands));
        $result = $this->utils->getBrand('brand_dup');
        $this->assertSame('First', $result['name']);
    }

    public function testSaveBrandsReindexesArray(): void
    {
        $brands = [2 => ['id' => 'brand_y', 'name' => 'Y']];
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with(
                'appointments',
                BackendUtils::BRANDING_APP_CONFIG_KEY,
                json_encode(array_values($brands))
            );
        $this->utils->saveBrands($brands);
    }
}
