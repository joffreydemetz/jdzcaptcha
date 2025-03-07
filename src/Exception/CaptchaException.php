<?php

namespace JDZ\Captcha\Exception;

class CaptchaException extends \Exception
{
  public function toArray(): array
  {
    return [
      'success' => false,
      'errorCode' => $this->getCode(),
      'errorMessage' => $this->getMessage(),
      'error' => $this->getCode(),
      'error_message' => $this->getMessage(),
    ];
  }

  public function toJson(): string
  {
    return json_encode($this->toArray());
  }

  public function toResponse(): string
  {
    return base64_encode($this->toJson());
  }
}
