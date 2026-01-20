<?php

namespace JDZ\Captcha;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\CaptchaToken;
use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Captcha\Exception\CaptchaTokenException;
use JDZ\Captcha\Exception\CaptchaBadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptchaApp
{
  public Request $request;
  public CaptchaConfig $config;
  public CaptchaToken $token;
  public Captcha $captcha;
  public string $nS;

  public function __construct(Request $request)
  {
    $this->request = $request;
    $this->config = new CaptchaConfig();
    $this->captcha = new Captcha();
    $this->captcha->app = $this;
  }

  public function init(): static
  {
    $this->nS = $this->config->get('nameSpace');

    $this->token = new CaptchaToken($this->config->getBool('token'), $this->request->getSession()->get($this->nS . '.csrf'));

    $this->request->getSession()->set($this->nS . '.csrf', $this->token->make());

    $this->cleanSessionData();
    $this->checkIconSet();

    return $this;
  }

  public function process(): void
  {
    try {
      $path = $this->request->getPathInfo();
      $method = $this->request->getMethod();

      if ('/captcha/load/' === $path) {
        if ('POST' === $method) {
          $this->jsonResponse($this->config->getJsConfig(), Response::HTTP_OK, ['content-type' => 'application/json']);
        }

        throw new CaptchaBadRequestException('Bad controller request');
      }

      if ('/captcha/request/' === $path) {
        // load the captcha image
        if ('GET' === $method) {
          if (true === $this->request->isXmlHttpRequest()) {
            throw new CaptchaBadRequestException('Is ajax request');
          }

          if (!($_payload = $this->request->query->get('payload'))) {
            throw new CaptchaBadRequestException('Missing payload');
          }

          $payload = $this->decodePayload(urldecode($_payload));
          if (!isset($payload['i']) || !is_numeric($payload['i'])) {
            throw new CaptchaBadRequestException('Error validating the payload');
          }

          if (false === $this->validToken($payload, false)) {
            throw new CaptchaTokenException('Error validating the token', 2);
          }

          if (!($generatedImage = $this->captcha->getImage($payload['i']))) {
            throw new CaptchaBadRequestException('Error generating Icons image');
          }

          $this->streamResponse($generatedImage);
        }

        if ('POST' === $method) {
          if (false === $this->request->isXmlHttpRequest()) {
            throw new CaptchaBadRequestException('Is not ajax request');
          }

          if (!($_payload = $this->request->request->get('payload'))) {
            throw new CaptchaBadRequestException('Missing payload');
          }

          $payload = $this->decodePayload($_payload);

          if (!isset($payload['a']) || !isset($payload['i']) || !is_numeric($payload['a']) || !is_numeric($payload['i'])) {
            throw new CaptchaBadRequestException('Error validating the posted payload');
          }

          if (false === $this->validToken($payload, true)) {
            throw new CaptchaTokenException('Error validating the posted token', 2);
          }

          $action = (int)$payload['a'];

          if (!in_array($action, [1, 2, 3])) {
            throw new CaptchaBadRequestException('Unrecognized payload action');
          }

          // Requesting the image hashes
          if (1 === $action) {
            $theme = !empty($payload['t']) ? $payload['t'] : 'light';
            $data = $this->captcha->getCaptchaData($theme, $payload['i']);
            $this->response($data, Response::HTTP_OK, ['content-type' => 'text/plain']);
          }

          // Setting the user's choice
          if (2 === $action) {
            if (true === $this->captcha->setSelectedAnswer($payload)) {
              $this->jsonResponse(new CaptchaResponse(true));
            }
            $this->jsonResponse(new CaptchaResponse(false, 100, 'Bad user choice'));
          }

          // Captcha interaction time expired.
          if (3 === $action) {
            $this->request->getSession()->remove($this->nS . '.' . $payload['i']);
            $this->response();
          }
        }

        throw new CaptchaBadRequestException('Bad controller request');
      }
    } catch (CaptchaBadRequestException $e) {

      $this->response('', Response::HTTP_BAD_REQUEST, []);
    } catch (CaptchaException $e) {

      $this->response($e->toResponse(), Response::HTTP_OK, ['content-type' => 'text/plain']);
    } catch (\Exception $e) {

      // $exception = new \CaptchaException($e->getMessage(), $e->getCode(), $e);
      // $this->response($exception->toResponse(), Response::HTTP_BAD_REQUEST, [ 'content-type' => 'text/plain' ]);

      die($e->getMessage());
    }
  }

  protected function cleanSessionData(): void
  {
    $sessionData = $this->request->getSession()->all();

    foreach ($sessionData as $key => $value) {
      if (!preg_match("/^" . $this->nS . "\.(.+)$/", $key, $m)) {
        continue;
      }

      if ('csrf' === $m[1]) {
        continue;
      }

      $identifier = (int)$m[1];

      if (empty($value['ts'])) {
        $this->request->getSession()->remove($key);
        continue;
      }

      /* if ( empty($value['correctId']) ){
        $this->request->getSession()->remove($key);
        continue;
      } */

      $data = new CaptchaData($identifier, $value);
      if (true === $data->isExpired()) {
        $this->request->getSession()->remove($key);
        continue;
      }
    }
  }

  protected function checkIconSet(int $tries = 0): void
  {
    $path = realpath($this->config->get('iconPath')) . '/';

    if (!is_dir($path)) {
      throw new CaptchaException('JdzCaptcha icon set not found in ' . $this->config->get('iconPath'));
    }

    $fi = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
    $count = \iterator_count($fi);
    if ($count < 10) {
      $this->config->set('theme', 'lc');
      $this->config->set('variant', 'light');
      $this->checkIconSet($tries++);
      return;
    }

    $this->config->set('nbIcons', $count);
  }

  protected function response(string $content = '', int $status = Response::HTTP_OK, array $headers = []): void
  {
    $response = new Response($content, $status, $headers);
    $response->send();
    exit();
  }

  protected function jsonResponse(CaptchaException|CaptchaResponse|array|string $data, int $status = Response::HTTP_OK, array $headers = []): void
  {
    $headers['content-type'] = 'application/json';

    if ($data instanceof CaptchaResponse || \is_array($data)) {
      $data = json_encode($data);
    } elseif ($data instanceof CaptchaException) {
      $data = $data->toJson();
    }

    $this->response($data, $status, $headers);
  }

  protected function streamResponse($generatedImage, int $status = Response::HTTP_OK, array $headers = []): void
  {
    header('Content-type: image/png');

    // Disable caching of the image.
    header('Expires: 0');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    // Show the image and exit the code
    imagepng($generatedImage);
    imagedestroy($generatedImage);

    exit();
  }

  protected function validToken(array $payload, bool $checkHeader): bool
  {
    $payloadToken = null;
    $headerToken = null;

    if (!empty($payload['tk'])) {
      $payloadToken = $payload['tk'];
    }

    if ($checkHeader && ($token = $this->request->server->get('HTTP_X_JDZCAPTCHA_TOKEN'))) {
      $headerToken = $token;
    }

    return $this->token->validate($payloadToken, $headerToken);
  }

  protected function decodePayload(string $payload): array
  {
    if (false === ($payload = base64_decode($payload))) {
      throw new \Exception('Error decoding the payload');
    }

    return json_decode($payload, true);
  }
}
