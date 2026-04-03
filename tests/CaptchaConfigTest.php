<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\CaptchaConfig;
use PHPUnit\Framework\TestCase;

class CaptchaConfigTest extends TestCase
{
  public function testDefaultValues(): void
  {
    $config = new CaptchaConfig();

    $this->assertFalse($config->getBool('debug'));
    $this->assertEquals('en', $config->get('lang'));
    $this->assertTrue($config->getBool('token'));
    $this->assertEquals('jdzcaptcha', $config->get('nameSpace'));
    $this->assertEquals('lc', $config->get('theme'));
    $this->assertEquals('light', $config->get('variant'));
    $this->assertEquals(5, $config->getInt('image.amount.min'));
    $this->assertEquals(8, $config->getInt('image.amount.max'));
    $this->assertEquals(3, $config->getInt('attempts.amount'));
    $this->assertEquals(60, $config->getInt('attempts.timeout'));
  }

  public function testDefaultIconPath(): void
  {
    $config = new CaptchaConfig();

    $iconPath = $config->get('iconPath');
    $placeholder = $config->get('placeholder');

    $this->assertStringEndsWith('assets/icons', $iconPath);
    $this->assertStringEndsWith('assets/placeholder.png', $placeholder);
  }

  public function testSetAndGet(): void
  {
    $config = new CaptchaConfig();

    $config->set('debug', true);
    $this->assertTrue($config->getBool('debug'));

    $config->set('lang', 'fr');
    $this->assertEquals('fr', $config->get('lang'));
  }

  public function testGetJsConfigReturnsOnlyNonDefaults(): void
  {
    $config = new CaptchaConfig();

    // With all defaults, JS config should be empty
    $jsConfig = $config->getJsConfig();
    $this->assertEmpty($jsConfig);

    // Change a value from default
    $config->set('theme', 'streamline');
    $jsConfig = $config->getJsConfig();
    $this->assertArrayHasKey('theme', $jsConfig);
    $this->assertEquals('streamline', $jsConfig['theme']);
  }
}
