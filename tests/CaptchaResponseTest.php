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

  public function testJsonSerialize(): void
  {
    $response = new CaptchaResponse(true);
    $json = json_encode($response);
    $decoded = json_decode($json, true);

    $this->assertTrue($decoded['success']);
    $this->assertEquals(0, $decoded['errorCode']);
    $this->assertEquals('', $decoded['errorMessage']);
  }

  public function testToArray(): void
  {
    $response = new CaptchaResponse(false, 5, 'Test error');
    $array = $response->toArray();

    $this->assertArrayHasKey('success', $array);
    $this->assertArrayHasKey('errorCode', $array);
    $this->assertArrayHasKey('errorMessage', $array);
    $this->assertFalse($array['success']);
  }
}
