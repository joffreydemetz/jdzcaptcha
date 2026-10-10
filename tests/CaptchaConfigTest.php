<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Exception\CaptchaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CaptchaConfigTest extends TestCase
{
  private function expectExactExceptionMessage(string $message): void
  {
    $this->expectExceptionMessageMatches('/^' . preg_quote($message, '/') . '$/');
  }

  public function testDefaultValues(): void
  {
    $config = new CaptchaConfig();

    $this->assertFalse($config->getBool('debug'));
    $this->assertEquals('en', $config->get('lang'));
    $this->assertTrue($config->getBool('token'));
    $this->assertEquals('jdzcaptcha', $config->get('nameSpace'));
    $this->assertEquals('streamline', $config->get('series'));
    $this->assertEquals('light', $config->get('theme'));
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

    $this->assertStringEndsWith('assets/jdzcaptcha', $iconPath);
    $this->assertStringEndsWith('assets/jdzcaptcha/placeholder.png', $placeholder);
    $this->assertDirectoryExists($iconPath . '/streamline/light', 'The default series/theme ships with the package');
    $this->assertFileExists($placeholder);
  }

  public function testGetJsConfigReturnsOnlyNonDefaults(): void
  {
    $config = new CaptchaConfig();

    // With all defaults, JS config should be empty
    $jsConfig = $config->getJsConfig();
    $this->assertEmpty($jsConfig);

    // Setting a default value changes nothing, a different one is exported alone
    $config->set('series', 'streamline');
    $this->assertSame([], $config->getJsConfig());

    $config->set('theme', 'dark');
    $this->assertSame(['theme' => 'dark'], $config->getJsConfig());
  }

  public function testLoadFromFileMergesOverridesIntoTheDefaults(): void
  {
    $config = new CaptchaConfig();

    $config->loadFromFile(__DIR__ . '/fixtures/config-override.yml');

    $this->assertSame('dark', $config->get('theme'));
    $this->assertSame(5, $config->get('attempts.amount'));
    $this->assertSame(60, $config->get('attempts.timeout'), 'A partial section keeps its other defaults');
    $this->assertSame('streamline', $config->get('series'));
  }

  public static function unreadableYamlProvider(): array
  {
    return [
      'malformed' => [__DIR__ . '/fixtures/config-invalid.yml', '/^Unable to parse the YAML application config : Malformed inline YAML string/'],
      'missing' => [__DIR__ . '/fixtures/config-missing.yml', '/^Unable to parse the YAML application config : File ".*config-missing\.yml" does not exist\.$/'],
    ];
  }

  #[DataProvider('unreadableYamlProvider')]
  public function testLoadFromFileRefusesAnUnreadableFile(string $path, string $message): void
  {
    $config = new CaptchaConfig();

    $this->expectException(CaptchaException::class);
    $this->expectExceptionMessageMatches($message);

    $config->loadFromFile($path);
  }

  public function testCheckAcceptsTheShippedAssets(): void
  {
    $this->expectNotToPerformAssertions();

    (new CaptchaConfig())->check();
  }

  public function testCheckRefusesAMissingIconSet(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', '/nonexistent/path');

    $this->expectException(CaptchaException::class);
    $this->expectExactExceptionMessage('Invalid iconPath: /nonexistent/path/streamline/light');

    $config->check();
  }

  public static function badPlaceholderProvider(): array
  {
    return [
      'missing file' => [__DIR__ . '/fixtures/missing.png'],
      'a directory' => [__DIR__ . '/fixtures/icons'],
    ];
  }

  #[DataProvider('badPlaceholderProvider')]
  public function testCheckRefusesABadPlaceholder(string $placeholder): void
  {
    $config = new CaptchaConfig();
    $config->set('placeholder', $placeholder);

    $this->expectException(CaptchaException::class);
    $this->expectExactExceptionMessage('Invalid placeholder path: ' . $placeholder);

    $config->check();
  }
}
