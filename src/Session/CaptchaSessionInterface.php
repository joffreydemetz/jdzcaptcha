<?php

namespace JDZ\Captcha\Session;

interface CaptchaSessionInterface
{
  public function get(string $key, mixed $default = null): mixed;
  public function set(string $key, mixed $value): void;
  public function has(string $key): bool;
  public function remove(string $key): void;
  public function all(): array;
}
