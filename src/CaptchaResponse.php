<?php

namespace JDZ\Captcha;

class CaptchaResponse implements \JsonSerializable
{
  public bool $success;
  public int $errorCode;
  public string $errorMessage;

  public function __construct(bool $success = false, int $errorCode = 0, string $errorMessage = '')
  {
    if ($errorCode > 0 && '' !== $errorMessage) {
      $success = false;
    }

    if (true === $success) {
      $errorCode = 0;
      $errorMessage = '';
    }

    $this->success = $success;
    $this->errorCode = $errorCode;
    $this->errorMessage = $errorMessage;
  }

  public function toArray(): array
  {
    return [
      'success' => $this->success,
      'errorCode' => $this->errorCode,
      'errorMessage' => $this->errorMessage,
    ];
  }

  public function jsonSerialize(): mixed
  {
    return $this->toArray();
  }
}
