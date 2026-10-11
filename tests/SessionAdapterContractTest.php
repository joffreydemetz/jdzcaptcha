<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Session\CaptchaSessionInterface;
use JDZ\Captcha\Session\NativeSession;
use JDZ\Captcha\Session\PsrSession;
use JDZ\Captcha\Session\SymfonySession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * One contract for the three session adapters. has() is what validate() asks
 * before anything else ("is there a puzzle under this id ?"): it used to mean
 * isset() for Native and PSR and array_key_exists() for Symfony, so a stored
 * null read as present on one and absent on the others.
 */
class SessionAdapterContractTest extends TestCase
{
  private ?array $savedSession = null;

  protected function setUp(): void
  {
    $this->savedSession = $_SESSION ?? null;
    $_SESSION = [];
  }

  protected function tearDown(): void
  {
    if (null === $this->savedSession) {
      unset($_SESSION);
    } else {
      $_SESSION = $this->savedSession;
    }
  }

  public static function adapters(): array
  {
    return [
      // the constructor starts a PHP session; NativeSessionTest covers it
      'native' => [static fn(): CaptchaSessionInterface => (new \ReflectionClass(NativeSession::class))->newInstanceWithoutConstructor()],
      'psr' => [static fn(): CaptchaSessionInterface => new PsrSession()],
      'symfony' => [static fn(): CaptchaSessionInterface => new SymfonySession(new Session(new MockArraySessionStorage()))],
    ];
  }

  #[DataProvider('adapters')]
  public function testAMissingKeyIsAbsent(\Closure $adapter): void
  {
    $session = $adapter();

    $this->assertFalse($session->has('jdzcaptcha.1'));
    $this->assertNull($session->get('jdzcaptcha.1'));
    $this->assertSame('fallback', $session->get('jdzcaptcha.1', 'fallback'));
  }

  #[DataProvider('adapters')]
  public function testAStoredNullIsAbsent(\Closure $adapter): void
  {
    $session = $adapter();
    $session->set('jdzcaptcha.1', null);

    $this->assertFalse($session->has('jdzcaptcha.1'));
  }

  public static function presentValues(): array
  {
    return [
      'a puzzle' => [['ts' => 1]],
      'an empty array' => [[]],
      'an empty string' => [''],
      'zero' => [0],
      'false' => [false],
      'a token' => ['abc'],
    ];
  }

  #[DataProvider('presentValues')]
  public function testAnyOtherStoredValueIsPresent(mixed $value): void
  {
    foreach (self::adapters() as $name => [$adapter]) {
      $session = $adapter();
      $session->set('jdzcaptcha.1', $value);

      $this->assertTrue($session->has('jdzcaptcha.1'), $name);
      $this->assertSame($value, $session->get('jdzcaptcha.1'), $name);
    }
  }

  #[DataProvider('adapters')]
  public function testRemoveAndAll(\Closure $adapter): void
  {
    $session = $adapter();
    $session->set('jdzcaptcha.csrf', 'abc');
    $session->set('jdzcaptcha.1', ['ts' => 1]);

    $session->remove('jdzcaptcha.csrf');

    $this->assertFalse($session->has('jdzcaptcha.csrf'));
    $this->assertSame(['jdzcaptcha.1' => ['ts' => 1]], $session->all());
  }
}
