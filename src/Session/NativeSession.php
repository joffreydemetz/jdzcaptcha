<?php

namespace JDZ\Captcha\Session;

class NativeSession implements CaptchaSessionInterface
{
  public function __construct()
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }

  public function get(string $key, mixed $default = null): mixed
  {
    return $_SESSION[$key] ?? $default;
  }

  public function set(string $key, mixed $value): void
  {
    $_SESSION[$key] = $value;
  }

  public function has(string $key): bool
  {
    return isset($_SESSION[$key]);
  }

  public function remove(string $key): void
  {
    unset($_SESSION[$key]);
  }

  public function all(): array
  {
    return $_SESSION;
  }
}
