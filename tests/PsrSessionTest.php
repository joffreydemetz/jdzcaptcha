<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Session\PsrSession;
use PHPUnit\Framework\TestCase;

/**
 * PsrSession needs no PSR package: it works on $_SESSION, which the app's
 * session middleware is expected to have started.
 */
class PsrSessionTest extends TestCase
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

  public function testNeverStartsASession(): void
  {
    $this->assertSame(\PHP_SESSION_NONE, session_status());

    (new PsrSession())->set('jdzcaptcha.csrf', 'abc');

    $this->assertSame(\PHP_SESSION_NONE, session_status());
  }

  public function testReadsAndWritesTheGlobalSessionArray(): void
  {
    $session = new PsrSession();

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
}
