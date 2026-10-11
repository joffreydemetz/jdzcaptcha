<?php

namespace JDZ\Captcha\Session;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SymfonySession implements CaptchaSessionInterface
{
  private SessionInterface $session;

  public function __construct(SessionInterface $session)
  {
    $this->session = $session;
  }

  public function get(string $key, mixed $default = null): mixed
  {
    return $this->session->get($key, $default);
  }

  public function set(string $key, mixed $value): void
  {
    $this->session->set($key, $value);
  }

  /**
   * Same meaning as the other adapters (isset): a stored null is absent.
   * Symfony's own has() is array_key_exists().
   */
  public function has(string $key): bool
  {
    return null !== $this->session->get($key);
  }

  public function remove(string $key): void
  {
    $this->session->remove($key);
  }

  public function all(): array
  {
    return $this->session->all();
  }
}
