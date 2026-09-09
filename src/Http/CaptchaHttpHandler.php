<?php

namespace JDZ\Captcha\Http;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaResponse;
use JDZ\Captcha\Exception\CaptchaException;

/**
 * The HTTP layer 2.x dropped, so consumers stop rewriting it: the widget's
 * three endpoints as framework-free methods returning an HttpResult the
 * consumer maps onto its own response type.
 *
 *   POST /captcha/load     -> load()
 *   GET  /captcha/request  -> image($queryParams['payload'])
 *   POST /captcha/request  -> action((string) $request->getBody(), $headerToken)
 *
 * Ported from a site CaptchaController (itself a port of the legacy
 * Callisto\Model\JdzCaptchaFrontModelTrait). Error mapping: an invalid or
 * replayed image request throws CaptchaException -> 403; every other bad
 * input -> 400. Success bodies carry their content-type in headers; the
 * consumer adds transport concerns (the image's no-cache headers, CORS...).
 */
class CaptchaHttpHandler
{
  public function __construct(private Captcha $captcha) {}

  /**
   * The widget's configuration (POST /captcha/load).
   */
  public function load(): HttpResult
  {
    return new HttpResult(200, (string) json_encode($this->captcha->getJsConfig()), [
      'content-type' => 'application/json',
    ]);
  }

  /**
   * The challenge image (GET /captcha/request?payload=...). Single use,
   * loaded by the widget as a background-image url.
   *
   * @param string $rawPayload the urldecoded `payload` query parameter
   */
  public function image(string $rawPayload): HttpResult
  {
    try {
      return $this->imageResult($rawPayload);
    } catch (CaptchaException $e) {
      return new HttpResult(403);
    } catch (\Throwable $e) {
      return new HttpResult(400);
    }
  }

  private function imageResult(string $rawPayload): HttpResult
  {
    $payload = $this->payload($rawPayload);

    if (!isset($payload['i']) || !is_numeric($payload['i'])) {
      return new HttpResult(400);
    }

    if (false === $this->captcha->validateToken($payload['tk'] ?? null)) {
      return new HttpResult(400);
    }

    $image = $this->captcha->getImage((int) $payload['i']);

    if (false === $image) {
      return new HttpResult(400);
    }

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return new HttpResult(200, (string) ob_get_clean(), [
      'content-type' => 'image/png',
    ]);
  }

  /**
   * The widget's ajax actions (POST /captcha/request): a=1 image hashes,
   * a=2 answer selection, a=3 interaction timeout.
   *
   * @param string $rawBody     the raw JSON request body ({"payload": "<base64>"})
   * @param string $headerToken the X-JdzCaptcha-Token header value
   */
  public function action(string $rawBody, string $headerToken): HttpResult
  {
    try {
      $body = json_decode($rawBody, true);
      $payload = $this->payload(\is_array($body) ? (string) ($body['payload'] ?? '') : '');

      if (!isset($payload['a'], $payload['i']) || !is_numeric($payload['a']) || !is_numeric($payload['i'])) {
        return new HttpResult(400);
      }

      if (false === $this->captcha->validateToken($payload['tk'] ?? null, $headerToken)) {
        return new HttpResult(400);
      }

      switch ((int) $payload['a']) {
        // requesting the image hashes
        case 1:
          $data = $this->captcha->getCaptchaData((string) $this->captcha->config->get('theme'), (int) $payload['i']);

          return new HttpResult(200, $data, ['content-type' => 'text/plain']);

          // setting the user's choice
        case 2:
          $result = true === $this->captcha->setSelectedAnswer($payload)
            ? new CaptchaResponse(true)
            : new CaptchaResponse(false, 100, 'Bad user choice');

          return new HttpResult(200, (string) json_encode($result), ['content-type' => 'application/json']);

          // captcha interaction time expired
        case 3:
          $this->captcha->invalidate((int) $payload['i']);

          return new HttpResult(200);
      }

      return new HttpResult(400);
    } catch (\Throwable $e) {
      return new HttpResult(400);
    }
  }

  /**
   * The widget's base64(json) payload envelope; [] on anything malformed.
   */
  private function payload(string $payload): array
  {
    if ('' === $payload || false === ($decoded = base64_decode($payload, true))) {
      return [];
    }

    $data = json_decode($decoded, true);

    return \is_array($data) ? $data : [];
  }
}
