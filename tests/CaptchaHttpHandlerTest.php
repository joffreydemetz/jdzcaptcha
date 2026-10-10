<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Captcha\Http\CaptchaHttpHandler;
use PHPUnit\Framework\TestCase;

class CaptchaHttpHandlerTest extends TestCase
{
  private function createHandler(array $configOverrides = []): array
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');

    foreach ($configOverrides as $key => $value) {
      $config->set($key, $value);
    }

    $session = new ArraySession();
    $captcha = new Captcha($config, $session);
    $captcha->init();

    return [new CaptchaHttpHandler($captcha), $captcha, $session];
  }

  private function envelope(array $payload): string
  {
    return base64_encode((string) json_encode($payload));
  }

  private function body(array $payload): string
  {
    return (string) json_encode(['payload' => $this->envelope($payload)]);
  }

  /**
   * The centre of the first slot showing the correct icon, as the widget reports a click.
   */
  private function correctClick(ArraySession $session, int $id): array
  {
    $puzzle = $session->get('jdzcaptcha.' . $id);
    $position = array_search($puzzle['correctId'], $puzzle['icons'], true);

    return ['x' => (int)(($position - 0.5) * 320 / count($puzzle['icons'])), 'y' => 25, 'w' => 320];
  }

  public function testLoadReturnsTheJsConfigAsJson(): void
  {
    [$handler, $captcha] = $this->createHandler();

    $result = $handler->load();

    $this->assertSame(200, $result->status);
    $this->assertSame('application/json', $result->headers['content-type']);
    $this->assertSame($captcha->getJsConfig(), json_decode($result->body, true));
  }

  public function testActionRejectsANonJsonBody(): void
  {
    [$handler] = $this->createHandler();

    $this->assertSame(400, $handler->action('not json at all', '')->status);
  }

  public function testActionRejectsAMissingActionOrIdentifier(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $this->assertSame(400, $handler->action($this->body(['i' => 1, 'tk' => $token]), $token)->status);
    $this->assertSame(400, $handler->action($this->body(['a' => 1, 'tk' => $token]), $token)->status);
  }

  public function testActionRejectsABadToken(): void
  {
    [$handler] = $this->createHandler();

    $result = $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => 'wrong']), 'wrong');

    $this->assertSame(400, $result->status);
  }

  public function testActionRejectsAMismatchedHeaderToken(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $result = $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), 'other');

    $this->assertSame(400, $result->status);
  }

  public function testActionRejectsAnUnknownAction(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $result = $handler->action($this->body(['a' => 9, 'i' => 1, 'tk' => $token]), $token);

    $this->assertSame(400, $result->status);
  }

  public function testActionOneReturnsTheCaptchaData(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $result = $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);

    $this->assertSame(200, $result->status);
    $this->assertSame('text/plain', $result->headers['content-type']);
    $this->assertSame(['id' => 1], json_decode((string) base64_decode($result->body, true), true));
  }

  public function testActionMapsAnyCaptchaFailureToABadRequest(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');
    $captcha = new class($config, new ArraySession()) extends Captcha {
      public function getCaptchaData(string $theme, int $identifier): string
      {
        throw new CaptchaException('Image already requested', 403);
      }
    };
    $captcha->init();
    $handler = new CaptchaHttpHandler($captcha);
    $token = $captcha->getToken()->make();

    $result = $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);

    // Unlike image(), action() has no 403: a CaptchaException is a bad request like any other failure.
    $this->assertSame(400, $result->status);
    $this->assertSame('', $result->body);
  }

  public function testActionTwoAcceptsTheCorrectIcon(): void
  {
    [$handler, $captcha, $session] = $this->createHandler();
    $token = $captcha->getToken()->make();
    $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);

    $result = $handler->action($this->body(['a' => 2, 'i' => 1, 'tk' => $token] + $this->correctClick($session, 1)), $token);

    $this->assertSame(200, $result->status);
    $this->assertSame('application/json', $result->headers['content-type']);
    $this->assertSame('{"success":true,"errorCode":0,"errorMessage":""}', $result->body);
    $this->assertTrue($session->get('jdzcaptcha.1')['completed']);
  }

  public function testActionTwoReportsABadChoice(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    // Puzzle data exists, but this answer names no icon.
    $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);
    $result = $handler->action($this->body(['a' => 2, 'i' => 1, 'tk' => $token]), $token);

    $this->assertSame(200, $result->status);
    $this->assertSame('application/json', $result->headers['content-type']);
    $this->assertSame('{"success":false,"errorCode":100,"errorMessage":"Bad user choice"}', $result->body);
  }

  public function testActionThreeInvalidatesAndReturnsAnEmptyBody(): void
  {
    [$handler, $captcha, $session] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);
    $result = $handler->action($this->body(['a' => 3, 'i' => 1, 'tk' => $token]), $token);

    $this->assertSame(200, $result->status);
    $this->assertSame('', $result->body);
    $this->assertFalse($session->has('jdzcaptcha.1'));
  }

  public function testImageRejectsAMalformedPayload(): void
  {
    [$handler] = $this->createHandler();

    $this->assertSame(400, $handler->image('')->status);
    $this->assertSame(400, $handler->image('%%%not-base64%%%')->status);
    $this->assertSame(400, $handler->image(base64_encode('{"no":"identifier"}'))->status);
  }

  public function testImageRejectsABadToken(): void
  {
    [$handler] = $this->createHandler();

    $this->assertSame(400, $handler->image($this->envelope(['i' => 1, 'tk' => 'wrong']))->status);
  }

  public function testImageMapsAnUnexpectedFailureToABadRequest(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');
    $captcha = new class($config, new ArraySession()) extends Captcha {
      public function getImage(int $identifier): \GdImage|false
      {
        throw new \RuntimeException('boom');
      }
    };
    $captcha->init();
    $handler = new CaptchaHttpHandler($captcha);
    $token = $captcha->getToken()->make();

    $result = $handler->image(base64_encode((string) json_encode(['i' => 1, 'tk' => $token])));

    $this->assertSame(400, $result->status);
  }

  public function testImageRendersAPngForAnExistingPuzzle(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);
    $result = $handler->image($this->envelope(['i' => 1, 'tk' => $token]));

    $this->assertSame(200, $result->status);
    $this->assertSame('image/png', $result->headers['content-type']);
    $this->assertStringStartsWith("\x89PNG", $result->body);
  }

  public function testImageIsForbiddenOnceServed(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();
    $handler->action($this->body(['a' => 1, 'i' => 1, 'tk' => $token]), $token);
    $handler->image($this->envelope(['i' => 1, 'tk' => $token]));

    $result = $handler->image($this->envelope(['i' => 1, 'tk' => $token]));

    $this->assertSame(403, $result->status);
    $this->assertSame('', $result->body);
  }

  public function testImageIsForbiddenForAPuzzleNeverRequested(): void
  {
    [$handler, $captcha] = $this->createHandler();
    $token = $captcha->getToken()->make();

    $result = $handler->image($this->envelope(['i' => 7, 'tk' => $token]));

    $this->assertSame(403, $result->status);
    $this->assertSame('', $result->body);
  }

  public function testImageMapsAMissingImageToABadRequest(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');
    $captcha = new class($config, new ArraySession()) extends Captcha {
      public function getImage(int $identifier): \GdImage|false
      {
        return false;
      }
    };
    $captcha->init();
    $handler = new CaptchaHttpHandler($captcha);
    $token = $captcha->getToken()->make();

    $result = $handler->image($this->envelope(['i' => 1, 'tk' => $token]));

    $this->assertSame(400, $result->status);
    $this->assertSame('', $result->body);
  }
}
