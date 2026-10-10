<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Session\SymfonySession;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SymfonySessionTest extends TestCase
{
  public function testReadsAndWritesTheSymfonySession(): void
  {
    $symfony = new Session(new MockArraySessionStorage());
    $session = new SymfonySession($symfony);

    $session->set('jdzcaptcha.csrf', 'abc');
    $symfony->set('jdzcaptcha.1', ['ts' => 1]);

    $this->assertSame('abc', $symfony->get('jdzcaptcha.csrf'));
    $this->assertTrue($session->has('jdzcaptcha.1'));
    $this->assertSame(['ts' => 1], $session->get('jdzcaptcha.1'));
    $this->assertSame('fallback', $session->get('jdzcaptcha.2', 'fallback'));
    $this->assertSame(['jdzcaptcha.csrf' => 'abc', 'jdzcaptcha.1' => ['ts' => 1]], $session->all());

    $session->remove('jdzcaptcha.csrf');

    $this->assertFalse($symfony->has('jdzcaptcha.csrf'));
  }

  public function testCaptchaStoresItsStateUnderFlatKeys(): void
  {
    $symfony = new Session(new MockArraySessionStorage());
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');
    $captcha = (new Captcha($config, new SymfonySession($symfony)))->init();

    $captcha->getCaptchaData('light', 1);

    // Symfony's attribute bag does not nest on dots: "jdzcaptcha.1" stays one key.
    $this->assertSame($captcha->getToken()->make(), $symfony->get('jdzcaptcha.csrf'));
    $this->assertSame('streamline/light', $symfony->get('jdzcaptcha.1')['mode']);
  }
}
