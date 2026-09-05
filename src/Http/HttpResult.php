<?php

namespace JDZ\Captcha\Http;

/**
 * What an HTTP endpoint should send back, framework-free: the consumer maps
 * it onto its own response object (PSR-7, Symfony, plain header()/echo).
 */
class HttpResult
{
  /**
   * @param array<string,string> $headers lowercase header name => value
   */
  public function __construct(
    public readonly int $status,
    public readonly string $body = '',
    public readonly array $headers = []
  ) {}
}
