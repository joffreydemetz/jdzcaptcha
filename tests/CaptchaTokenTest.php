<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\CaptchaToken;
use PHPUnit\Framework\TestCase;

class CaptchaTokenTest extends TestCase
{
  public function testMakeGeneratesToken(): void
  {
    $token = new CaptchaToken(true, null);
    $value = $token->make();

    $this->assertNotEmpty($value);
    $this->assertEquals(40, strlen($value)); // 20 bytes = 40 hex chars
  }

  public function testMakeReturnsSameValueOnSecondCall(): void
  {
    $token = new CaptchaToken(true, null);
    $first = $token->make();
    $second = $token->make();

    $this->assertSame($first, $second);
  }

  public function testMakeReturnsExistingValue(): void
  {
    $token = new CaptchaToken(true, 'existing-token');
    $this->assertEquals('existing-token', $token->make());
  }

  public function testValidateWhenInactive(): void
  {
    $token = new CaptchaToken(false, 'some-value');

    // When token validation is disabled, always returns true
    $this->assertTrue($token->validate('wrong-value'));
    $this->assertTrue($token->validate(null));
  }

  public function testValidatePayloadToken(): void
  {
    $token = new CaptchaToken(true, 'secret');

    $this->assertTrue($token->validate('secret'));
    $this->assertFalse($token->validate('wrong'));
    $this->assertFalse($token->validate(null));
  }

  public function testValidateWithHeaderToken(): void
  {
    $token = new CaptchaToken(true, 'secret');

    $this->assertTrue($token->validate('secret', 'secret'));
    $this->assertFalse($token->validate('secret', 'wrong'));
    $this->assertFalse($token->validate('wrong', 'secret'));
  }

  public function testValidateWithEmptySessionToken(): void
  {
    $token = new CaptchaToken(true, null);

    // Empty session token should fail even with matching payload
    $this->assertFalse($token->validate(null));
  }

  public function testToString(): void
  {
    $token = new CaptchaToken(true, 'my-token');
    $this->assertEquals('my-token', (string)$token);
  }
}
