<?php
namespace Captcha;

class CaptchaToken
{
  public int $length = 20;
  public bool $active = false;
  public ?string $value = null;
  
  public function __construct(bool $active, ?string $value)
  {
    $this->active = $active;
    $this->value = $value;
  }
  
  public function __toString(): string
  {
    return (string)$this->value;
  }
  
  public function make(): string
  {
    if ( !$this->value ){
      try {
        $token = bin2hex(random_bytes($this->length));
      } catch (\Exception $e) {
        $token = str_shuffle(md5(uniqid(rand(), true)));
      }
      
      $this->value = $token;
    }
    
    return $this->value;
  }
  
  public function validate(string $payloadToken, ?string $headerToken=null): bool
  {
    if ( false === $this->active ){
      return true;
    }
    
    // If the token is empty but the option is enabled, the token was never requested.
    if ( empty($this->value) ){
      return false;
    }
    
    // Validate the payload and header token (if set) against the session token.
    if ( null !== $headerToken ){
      return $this->value === $payloadToken && $this->value === $headerToken;
    }
    
    return $this->value === $payloadToken;
  }
}
