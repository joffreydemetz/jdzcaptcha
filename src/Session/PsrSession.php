<?php

namespace JDZ\Captcha\Session;

/**
 * Session adapter for PSR-7/PSR-15 frameworks (Slim, Mezzio, etc.).
 *
 * Does NOT auto-start the session — the consuming app must ensure
 * the session is started via middleware before using this adapter.
 */
class PsrSession implements CaptchaSessionInterface
{
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
