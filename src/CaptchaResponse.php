<?php
namespace Captcha;

class CaptchaResponse implements \JsonSerializable
{
  public bool $success = false;
  public int $errorCode = 0;
  public string $errorMessage = '';
  
  public function __construct(bool $success=false, int $errorCode=0, string $errorMessage='')
  {
    if ( $errorCode > 0 && '' !== $errorMessage ){
      $success = false;
    }
    
    if ( true === $success ){
      $errorCode = 0;
      $errorMessage = '';
    }
    
    $this->success = $success;
    $this->errorCode = $errorCode;
    $this->errorMessage = $errorMessage;
  }
  
  public function toArray(): array
  {
    return get_object_vars($this);
  }
  
  public function jsonSerialize(): mixed
  {
    return [
      'success' => $this->success,
      'errorCode' => $this->errorCode,
      'errorMessage' => $this->errorMessage,
    ];
  }
}
