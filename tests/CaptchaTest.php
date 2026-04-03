<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Captcha\Exception\CaptchaValidationException;
use PHPUnit\Framework\TestCase;

class CaptchaTest extends TestCase
{
  private function createCaptcha(array $configOverrides = []): array
  {
    $config = new CaptchaConfig();

    // Point to a fake icon path for non-image tests
    $config->set('iconPath', __DIR__ . '/fixtures/icons');
    $config->set('placeholder', __DIR__ . '/fixtures/placeholder.png');

    foreach ($configOverrides as $key => $value) {
      $config->set($key, $value);
    }

    $session = new ArraySession();
    $captcha = new Captcha($config, $session);

    return [$captcha, $session, $config];
  }

  private function createInitializedCaptcha(array $configOverrides = []): array
  {
    [$captcha, $session, $config] = $this->createCaptcha($configOverrides);
    $captcha->init();
    return [$captcha, $session, $config];
  }

  public function testConstructor(): void
  {
    $config = new CaptchaConfig();
    $session = new ArraySession();

    $captcha = new Captcha($config, $session);

    $this->assertInstanceOf(Captcha::class, $captcha);
    $this->assertSame($config, $captcha->config);
  }

  public function testInitSetsTokenInSession(): void
  {
    [$captcha, $session] = $this->createCaptcha();
    $captcha->init();

    $this->assertTrue($session->has('jdzcaptcha.csrf'));
    $this->assertNotEmpty($session->get('jdzcaptcha.csrf'));
    $this->assertNotNull($captcha->getToken());
  }

  public function testInitCleansExpiredSessions(): void
  {
    [$captcha, $session] = $this->createCaptcha();

    // Add expired session data
    $session->set('jdzcaptcha.123', [
      'ts' => time() - 3600, // 1 hour ago
      'correctId' => 5,
    ]);

    // Add fresh session data
    $session->set('jdzcaptcha.456', [
      'ts' => time(),
      'correctId' => 3,
    ]);

    $captcha->init();

    $this->assertFalse($session->has('jdzcaptcha.123'));
    $this->assertTrue($session->has('jdzcaptcha.456'));
  }

  public function testInitThrowsOnMissingIconSet(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', '/nonexistent/path');
    $session = new ArraySession();
    $captcha = new Captcha($config, $session);

    $this->expectException(CaptchaException::class);
    $captcha->init();
  }

  public function testGetCaptchaDataReturnsBase64Json(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $result = $captcha->getCaptchaData('light', 1);

    $decoded = json_decode(base64_decode($result), true);
    $this->assertIsArray($decoded);
    $this->assertArrayHasKey('id', $decoded);
    $this->assertEquals(1, $decoded['id']);
  }

  public function testGetCaptchaDataStoresSessionState(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $captcha->getCaptchaData('light', 42);

    $sessionData = $session->get('jdzcaptcha.42');
    $this->assertIsArray($sessionData);
    $this->assertEquals('light', $sessionData['mode']);
    $this->assertNotEmpty($sessionData['icons']);
    $this->assertNotEmpty($sessionData['iconIds']);
    $this->assertGreaterThan(0, $sessionData['correctId']);
    $this->assertFalse($sessionData['requested']);
  }

  public function testGetCaptchaDataIconCount(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha([
      'image.amount.min' => 6,
      'image.amount.max' => 6, // Force exactly 6
    ]);

    $captcha->getCaptchaData('light', 1);

    $sessionData = $session->get('jdzcaptcha.1');
    $this->assertCount(6, $sessionData['icons']);
  }

  public function testGetCaptchaDataTimeoutReturnsError(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    // Simulate a timeout state
    $session->set('jdzcaptcha.1', [
      'attemptsTimeout' => time() + 30, // 30 seconds remaining
      'attempts' => 3,
      'ts' => time(),
    ]);

    $result = $captcha->getCaptchaData('light', 1);
    $decoded = json_decode(base64_decode($result), true);

    $this->assertArrayHasKey('error', $decoded);
    $this->assertEquals(1, $decoded['error']);
    $this->assertArrayHasKey('data', $decoded);
  }

  public function testSetSelectedAnswerCorrect(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $captcha->getCaptchaData('light', 1);
    $sessionData = $session->get('jdzcaptcha.1');

    // Find the correct icon position
    $correctId = $sessionData['correctId'];
    $correctPosition = array_search($correctId, $sessionData['icons']);
    $iconCount = count($sessionData['icons']);
    $containerWidth = 320;
    $iconWidth = $containerWidth / $iconCount;

    // Calculate click coordinates for the correct position
    $clickX = (int)(($correctPosition - 1) * $iconWidth + $iconWidth / 2);
    $clickY = 25;

    $result = $captcha->setSelectedAnswer([
      'i' => 1,
      'x' => $clickX,
      'y' => $clickY,
      'w' => $containerWidth,
    ]);

    $this->assertTrue($result);

    // Verify session updated
    $updatedData = $session->get('jdzcaptcha.1');
    $this->assertTrue($updatedData['completed']);
    $this->assertEquals(0, $updatedData['attempts']);
  }

  public function testSetSelectedAnswerIncorrect(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $captcha->getCaptchaData('light', 1);
    $sessionData = $session->get('jdzcaptcha.1');

    // Find an incorrect icon position
    $correctId = $sessionData['correctId'];
    $incorrectPosition = null;
    foreach ($sessionData['icons'] as $pos => $id) {
      if ($id !== $correctId) {
        $incorrectPosition = $pos;
        break;
      }
    }

    $iconCount = count($sessionData['icons']);
    $containerWidth = 320;
    $iconWidth = $containerWidth / $iconCount;
    $clickX = (int)(($incorrectPosition - 1) * $iconWidth + $iconWidth / 2);

    $result = $captcha->setSelectedAnswer([
      'i' => 1,
      'x' => $clickX,
      'y' => 25,
      'w' => $containerWidth,
    ]);

    $this->assertFalse($result);

    $updatedData = $session->get('jdzcaptcha.1');
    $this->assertFalse($updatedData['completed']);
    $this->assertEquals(1, $updatedData['attempts']);
  }

  public function testSetSelectedAnswerEmptyPayload(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->assertFalse($captcha->setSelectedAnswer([]));
  }

  public function testSetSelectedAnswerMissingFields(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->assertFalse($captcha->setSelectedAnswer(['i' => 1]));
  }

  public function testValidateTokenWhenDisabled(): void
  {
    [$captcha] = $this->createInitializedCaptcha(['token' => false]);

    $this->assertTrue($captcha->validateToken('anything'));
    $this->assertTrue($captcha->validateToken(null));
  }

  public function testValidateTokenCorrect(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $token = $session->get('jdzcaptcha.csrf');
    $this->assertTrue($captcha->validateToken($token));
  }

  public function testValidateTokenIncorrect(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->assertFalse($captcha->validateToken('wrong-token'));
  }

  public function testInvalidateRemovesSession(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $captcha->getCaptchaData('light', 99);
    $this->assertTrue($session->has('jdzcaptcha.99'));

    $captcha->invalidate(99);
    $this->assertFalse($session->has('jdzcaptcha.99'));
  }

  public function testValidateThrowsOnEmptyPost(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->expectException(CaptchaValidationException::class);
    $captcha->validate([]);
  }

  public function testValidateThrowsOnMissingId(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->expectException(CaptchaValidationException::class);
    $captcha->validate(['foo' => 'bar']);
  }

  public function testGetJsConfig(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $jsConfig = $captcha->getJsConfig();
    $this->assertIsArray($jsConfig);
  }
}
