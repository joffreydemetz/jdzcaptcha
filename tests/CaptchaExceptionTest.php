<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Exception\CaptchaException;
use PHPUnit\Framework\TestCase;

class CaptchaExceptionTest extends TestCase
{
  public function testToArrayCarriesBothKeyStyles(): void
  {
    $e = new CaptchaException('Image already requested', 403);

    $this->assertSame([
      'success' => false,
      'errorCode' => 403,
      'errorMessage' => 'Image already requested',
      'error' => 403,
      'error_message' => 'Image already requested',
    ], $e->toArray());
  }

  public function testToJson(): void
  {
    $e = new CaptchaException('Image already requested', 403);

    $this->assertSame(
      '{"success":false,"errorCode":403,"errorMessage":"Image already requested","error":403,"error_message":"Image already requested"}',
      $e->toJson()
    );
  }

  public function testToResponseIsBase64JsonKeepingAccents(): void
  {
    $e = new CaptchaException('Aucune image sélectionnée', 2);

    $this->assertSame([
      'success' => false,
      'errorCode' => 2,
      'errorMessage' => 'Aucune image sélectionnée',
      'error' => 2,
      'error_message' => 'Aucune image sélectionnée',
    ], json_decode((string) base64_decode($e->toResponse(), true), true));
  }
}
