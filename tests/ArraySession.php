<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Session\CaptchaSessionInterface;

class ArraySession implements CaptchaSessionInterface
{
  private array $data = [];

  public function get(string $key, mixed $default = null): mixed
  {
    return $this->data[$key] ?? $default;
  }

  public function set(string $key, mixed $value): void
  {
    $this->data[$key] = $value;
  }

  public function has(string $key): bool
  {
    return isset($this->data[$key]);
  }

  public function remove(string $key): void
  {
    unset($this->data[$key]);
  }

  public function all(): array
  {
    return $this->data;
  }
}
