<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\CaptchaResponse;
use PHPUnit\Framework\TestCase;

class CaptchaResponseTest extends TestCase
{
  public function testSuccessResponse(): void
  {
    $response = new CaptchaResponse(true);

    $this->assertTrue($response->success);
    $this->assertEquals(0, $response->errorCode);
    $this->assertEquals('', $response->errorMessage);
  }

  public function testErrorResponse(): void
  {
    $response = new CaptchaResponse(false, 100, 'Bad choice');

    $this->assertFalse($response->success);
    $this->assertEquals(100, $response->errorCode);
    $this->assertEquals('Bad choice', $response->errorMessage);
  }

  public function testErrorCodeOverridesSuccess(): void
  {
    $response = new CaptchaResponse(true, 1, 'Error');

    // Error code > 0 with message forces success to false
    $this->assertFalse($response->success);
  }
}
