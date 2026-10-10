<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Session\NativeSession;
use PHPUnit\Framework\TestCase;

/**
 * Runs a real PHP session, kept off the browser side (no cookie, no cache
 * headers) and off the shared save path (a temp dir), then puts everything back.
 */
class NativeSessionTest extends TestCase
{
  private const INI = ['session.save_path', 'session.use_cookies', 'session.cache_limiter'];

  private ?array $savedSession = null;
  private array $savedIni = [];
  private string $savePath;

  protected function setUp(): void
  {
    $this->savedSession = $_SESSION ?? null;

    foreach (self::INI as $key) {
      $this->savedIni[$key] = (string) ini_get($key);
    }

    $this->savePath = sys_get_temp_dir() . '/jdzcaptcha-session-' . bin2hex(random_bytes(6));
    mkdir($this->savePath);

    ini_set('session.save_path', $this->savePath);
    ini_set('session.use_cookies', '0');
    ini_set('session.cache_limiter', '');
  }

  protected function tearDown(): void
  {
    if (\PHP_SESSION_ACTIVE === session_status()) {
      session_destroy();
    }

    foreach ($this->savedIni as $key => $value) {
      ini_set($key, $value);
    }

    if (null === $this->savedSession) {
      unset($_SESSION);
    } else {
      $_SESSION = $this->savedSession;
    }

    foreach (glob($this->savePath . '/*') ?: [] as $file) {
      unlink($file);
    }
    rmdir($this->savePath);
  }

  public function testStartsTheSessionWhenNoneIsActive(): void
  {
    $this->assertSame(\PHP_SESSION_NONE, session_status());

    new NativeSession();

    $this->assertSame(\PHP_SESSION_ACTIVE, session_status());
  }

  public function testJoinsAnAlreadyActiveSession(): void
  {
    session_id('jdzcaptchatest');
    session_start();
    $_SESSION['app.user'] = 12;

    $session = new NativeSession();

    $this->assertSame('jdzcaptchatest', session_id());
    $this->assertSame(12, $session->get('app.user'));
  }

  public function testReadsAndWritesTheGlobalSessionArray(): void
  {
    $session = new NativeSession();

    $session->set('jdzcaptcha.csrf', 'abc');
    $_SESSION['jdzcaptcha.1'] = ['ts' => 1];

    $this->assertSame('abc', $_SESSION['jdzcaptcha.csrf']);
    $this->assertTrue($session->has('jdzcaptcha.1'));
    $this->assertSame(['ts' => 1], $session->get('jdzcaptcha.1'));
    $this->assertSame('fallback', $session->get('jdzcaptcha.2', 'fallback'));
    $this->assertSame(['jdzcaptcha.csrf' => 'abc', 'jdzcaptcha.1' => ['ts' => 1]], $session->all());

    $session->remove('jdzcaptcha.csrf');

    $this->assertArrayNotHasKey('jdzcaptcha.csrf', $_SESSION);
    $this->assertFalse($session->has('jdzcaptcha.csrf'));
  }

  public function testCaptchaDefaultsToTheNativeSession(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');

    $captcha = (new Captcha($config))->init();

    $this->assertSame($captcha->getToken()->make(), $_SESSION['jdzcaptcha.csrf']);
  }
}
