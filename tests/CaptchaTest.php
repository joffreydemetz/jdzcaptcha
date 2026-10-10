<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;
use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Captcha\Exception\CaptchaValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CaptchaTest extends TestCase
{
  private ?string $tempDir = null;

  protected function tearDown(): void
  {
    if (null === $this->tempDir) {
      return;
    }

    $entries = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
      $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($this->tempDir);
  }

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

  /**
   * Writes <tempDir>/streamline/<theme>/icon-1..<count>.png, each a plain square of one colour.
   */
  private function makeIconSet(string $theme, int $count, array $rgb): void
  {
    $this->tempDir ??= sys_get_temp_dir() . '/jdzcaptcha-' . bin2hex(random_bytes(6));
    $dir = $this->tempDir . '/streamline/' . $theme;
    mkdir($dir, 0777, true);

    for ($i = 1; $i <= $count; $i++) {
      $icon = imagecreatetruecolor(30, 30);
      imagefill($icon, 0, 0, imagecolorallocate($icon, $rgb[0], $rgb[1], $rgb[2]));
      imagepng($icon, $dir . '/icon-' . $i . '.png');
    }
  }

  /**
   * The x coordinate of the centre of an icon slot (1-based) on the 320px wide widget.
   */
  private static function clickX(int $position, int $iconCount): int
  {
    return (int)(($position - 0.5) * 320 / $iconCount);
  }

  /**
   * The first slot showing the correct icon, or the first one showing another icon.
   */
  private function slot(array $puzzle, bool $correct): int
  {
    foreach ($puzzle['icons'] as $position => $iconId) {
      if (($iconId === $puzzle['correctId']) === $correct) {
        return $position;
      }
    }

    $this->fail('The puzzle has no ' . ($correct ? 'correct' : 'wrong') . ' slot');
  }

  /**
   * The widget's answer payload (POST /captcha/request, a=2): a click on the correct or on a wrong icon.
   */
  private function answer(ArraySession $session, int $id, bool $correct): array
  {
    $puzzle = $session->get('jdzcaptcha.' . $id);

    return [
      'i' => $id,
      'x' => self::clickX($this->slot($puzzle, $correct), count($puzzle['icons'])),
      'y' => 25,
      'w' => 320,
    ];
  }

  /**
   * The form's hidden selection field ("x,y,width") on the correct or on a wrong icon.
   */
  private function selection(ArraySession $session, int $id, bool $correct): string
  {
    $answer = $this->answer($session, $id, $correct);

    return $answer['x'] . ',' . $answer['y'] . ',' . $answer['w'];
  }

  /**
   * A captcha solved through the widget flow, and the contact form post that goes with it.
   */
  private function solvedCaptcha(): array
  {
    [$captcha, $session] = $this->createInitializedCaptcha();
    $captcha->getCaptchaData('light', 1);
    $this->assertTrue($captcha->setSelectedAnswer($this->answer($session, 1, true)));

    $post = [
      '_jdzc-hf-id' => '1',
      '_jdzc-hf-hp' => '',
      '_jdzc-token' => $session->get('jdzcaptcha.csrf'),
      '_jdzc-hf-se' => $this->selection($session, 1, true),
    ];

    return [$captcha, $session, $post];
  }

  private function assertThrows(callable $call, string $class, int $code, string $message): void
  {
    try {
      $call();
    } catch (CaptchaException $e) {
      $this->assertSame([$class, $code, $message], [$e::class, $e->getCode(), $e->getMessage()]);
      return;
    }

    $this->fail($class . ' was not thrown');
  }

  public function testInitSetsTokenInSession(): void
  {
    [$captcha, $session] = $this->createCaptcha();
    $captcha->init();

    $token = $session->get('jdzcaptcha.csrf');
    $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $token);
    $this->assertSame($token, $captcha->getToken()->make());
  }

  public function testInitKeepsTheTokenAlreadyInTheSession(): void
  {
    [$captcha, $session] = $this->createCaptcha();
    $session->set('jdzcaptcha.csrf', 'issued-with-the-form');

    $captcha->init();

    $this->assertSame('issued-with-the-form', $captcha->getToken()->make());
    $this->assertSame('issued-with-the-form', $session->get('jdzcaptcha.csrf'));
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

  public function testInitDropsCaptchaEntriesWithoutATimestampAndLeavesTheAppAlone(): void
  {
    [$captcha, $session] = $this->createCaptcha();
    $session->set('jdzcaptcha.7', ['correctId' => 3]);
    $session->set('app.user', ['id' => 12]);

    $captcha->init();

    $this->assertFalse($session->has('jdzcaptcha.7'));
    $this->assertSame(['id' => 12], $session->get('app.user'));
  }

  public function testInitThrowsOnMissingIconSet(): void
  {
    $config = new CaptchaConfig();
    $config->set('iconPath', '/nonexistent/path');
    $session = new ArraySession();
    $captcha = new Captcha($config, $session);

    $this->assertThrows(
      fn() => $captcha->init(),
      CaptchaException::class,
      0,
      'JdzCaptcha icon set not found in /nonexistent/path/streamline/light'
    );
  }

  public function testInitRefusesAnIconSetOfFewerThanTenIcons(): void
  {
    $this->makeIconSet('light', 9, [0, 0, 0]);
    [$captcha] = $this->createCaptcha(['iconPath' => $this->tempDir]);

    $this->assertThrows(
      fn() => $captcha->init(),
      CaptchaException::class,
      0,
      'JdzCaptcha icon set has less than 10 icons in ' . $this->tempDir . '/streamline/light'
    );
  }

  public function testInitCountsTheIconsOfTheSet(): void
  {
    $this->makeIconSet('light', 10, [0, 0, 0]);
    [$captcha, , $config] = $this->createCaptcha(['iconPath' => $this->tempDir]);

    $captcha->init();

    $this->assertSame(10, $config->get('nbIcons'));
  }

  public function testGetCaptchaDataReturnsBase64Json(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $result = $captcha->getCaptchaData('light', 1);

    $this->assertSame(['id' => 1], json_decode(base64_decode($result), true));
  }

  public function testGetCaptchaDataStoresSessionState(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $captcha->getCaptchaData('light', 42);

    $sessionData = $session->get('jdzcaptcha.42');
    $this->assertIsArray($sessionData);
    $this->assertEquals('streamline/light', $sessionData['mode']);
    $this->assertNotEmpty($sessionData['icons']);
    $this->assertNotEmpty($sessionData['iconIds']);
    $this->assertGreaterThan(0, $sessionData['correctId']);
    $this->assertFalse($sessionData['requested']);
  }

  public static function iconAmountProvider(): array
  {
    return [
      '5 icons' => [5],
      '6 icons' => [6],
      '7 icons' => [7],
      '8 icons' => [8],
    ];
  }

  #[DataProvider('iconAmountProvider')]
  public function testTheCorrectIconIsTheOneShownTheLeastTimes(int $amount): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha([
      'image.amount.min' => $amount,
      'image.amount.max' => $amount,
    ]);

    // The layout is random: draw enough puzzles to go through every split of the slots.
    for ($draw = 0; $draw < 50; $draw++) {
      $captcha->getCaptchaData('light', 1);
      $puzzle = $session->get('jdzcaptcha.1');

      $slots = array_keys($puzzle['icons']);
      sort($slots);
      $this->assertSame(range(1, $amount), $slots);

      $shown = array_count_values($puzzle['icons']);
      $drawnIds = array_keys($shown);
      $listedIds = $puzzle['iconIds'];
      sort($drawnIds);
      sort($listedIds);
      $this->assertSame($drawnIds, $listedIds);
      $this->assertGreaterThanOrEqual(1, min($listedIds));
      $this->assertLessThanOrEqual(20, max($listedIds), 'Icon ids stay within the 20 icons of the set');

      $correctShown = $shown[$puzzle['correctId']];
      unset($shown[$puzzle['correctId']]);
      $this->assertNotEmpty($shown);
      $this->assertLessThan(min($shown), $correctShown);
    }
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

  public function testGetCaptchaDataLiftsAnExpiredLock(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();
    $session->set('jdzcaptcha.1', [
      'attempts' => 3,
      'attemptsTimeout' => time() - 1,
      'ts' => time() - 61,
    ]);

    $this->assertSame(['id' => 1], json_decode(base64_decode($captcha->getCaptchaData('light', 1)), true));

    $puzzle = $session->get('jdzcaptcha.1');
    $this->assertSame(0, $puzzle['attempts']);
    $this->assertSame(0, $puzzle['attemptsTimeout']);
    $this->assertGreaterThan(0, $puzzle['correctId']);
  }

  public static function lockOutProvider(): array
  {
    return [
      'defaults: 3 attempts, 60 s' => [[], 3, 60],
      '2 attempts, 30 s' => [['attempts.amount' => 2, 'attempts.timeout' => 30], 2, 30],
    ];
  }

  #[DataProvider('lockOutProvider')]
  public function testWrongAnswersLockTheCaptchaOnceTheLimitIsReached(array $config, int $amount, int $timeout): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha($config);

    // The widget asks for a fresh puzzle after each wrong answer: the count carries over.
    for ($attempt = 1; $attempt < $amount; $attempt++) {
      $captcha->getCaptchaData('light', 1);
      $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));
      $this->assertSame($attempt, $session->get('jdzcaptcha.1')['attempts']);
      $this->assertArrayNotHasKey('attemptsTimeout', $session->get('jdzcaptcha.1'));
    }

    $captcha->getCaptchaData('light', 1);
    $before = time();
    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));
    $after = time();

    $locked = $session->get('jdzcaptcha.1');
    $this->assertSame($amount, $locked['attempts']);
    $this->assertGreaterThanOrEqual($before + $timeout, $locked['attemptsTimeout']);
    $this->assertLessThanOrEqual($after + $timeout, $locked['attemptsTimeout']);

    // No new puzzle while locked: the widget is told how long to wait, in milliseconds.
    $before = time();
    $reply = json_decode(base64_decode($captcha->getCaptchaData('light', 1)), true);
    $after = time();

    $this->assertSame(1, $reply['error']);
    $this->assertGreaterThanOrEqual(($locked['attemptsTimeout'] - $after) * 1000, $reply['data']);
    $this->assertLessThanOrEqual(($locked['attemptsTimeout'] - $before) * 1000, $reply['data']);
    $this->assertSame($locked, $session->get('jdzcaptcha.1'));
  }

  public function testAZeroTimeoutNeverLocks(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha(['attempts.timeout' => 0]);

    for ($attempt = 1; $attempt <= 4; $attempt++) {
      $captcha->getCaptchaData('light', 1);
      $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));
    }

    $this->assertSame(4, $session->get('jdzcaptcha.1')['attempts']);
    $this->assertArrayNotHasKey('attemptsTimeout', $session->get('jdzcaptcha.1'));
    $this->assertSame(['id' => 1], json_decode(base64_decode($captcha->getCaptchaData('light', 1)), true));
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

  public function testGetImageServesEachPuzzleOnce(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();
    $captcha->getCaptchaData('light', 1);

    $image = $captcha->getImage(1);

    $this->assertNotFalse($image);
    $this->assertSame([320, 50], [imagesx($image), imagesy($image)]);
    $this->assertTrue($session->get('jdzcaptcha.1')['requested']);
    $this->assertThrows(fn() => $captcha->getImage(1), CaptchaException::class, 403, 'Image already requested');
  }

  public function testGetImageRefusesAnUnknownPuzzle(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $this->assertThrows(fn() => $captcha->getImage(5), CaptchaException::class, 403, 'Invalid captcha state');
    $this->assertFalse($session->has('jdzcaptcha.5'));
  }

  public function testGetImageDrawsThePuzzleInTheThemeItWasCreatedWith(): void
  {
    $this->makeIconSet('light', 10, [255, 0, 0]);
    $this->makeIconSet('dark', 10, [0, 0, 255]);
    [$captcha] = $this->createInitializedCaptcha([
      'iconPath' => $this->tempDir,
      'image.amount.min' => 5,
      'image.amount.max' => 5,
    ]);
    $captcha->getCaptchaData('dark', 1);

    $image = $captcha->getImage(1);

    // The config still says "light"; the first icon is drawn at x 17..46, y 10..39.
    $this->assertSame(0x0000FF, imagecolorat($image, 31, 25));
  }

  public static function separatorColourProvider(): array
  {
    return [
      'default grey' => [[], 0xF0F0F0],
      'container border' => [['container.border' => [1, 2, 3]], 0x010203],
      'theme border first' => [['container.border' => [1, 2, 3], 'themes.light.border' => [4, 5, 6]], 0x040506],
      'malformed theme border ignored' => [['container.border' => [1, 2, 3], 'themes.light.border' => [4, 5]], 0x010203],
      'borders off' => [['image.border' => false], 0xFFFFFF],
    ];
  }

  #[DataProvider('separatorColourProvider')]
  public function testImageSeparatorColour(array $config, int $rgb): void
  {
    [$captcha] = $this->createInitializedCaptcha($config + ['image.amount.min' => 5, 'image.amount.max' => 5]);
    $captcha->getCaptchaData('light', 1);

    $image = $captcha->getImage(1);

    // 5 slots of 64px: the first separator is the x = 64 column, above the icons (drawn from y = 10).
    $this->assertSame($rgb, imagecolorat($image, 64, 0));
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

    $this->assertThrows(fn() => $captcha->validate([]), CaptchaValidationException::class, 3, 'Form is empty');
  }

  public function testValidateThrowsOnMissingId(): void
  {
    [$captcha] = $this->createInitializedCaptcha();

    $this->assertThrows(fn() => $captcha->validate(['foo' => 'bar']), CaptchaValidationException::class, 4, 'Invalid Captcha ID');
  }

  public function testValidateAcceptsASolvedCaptchaAndConsumesIt(): void
  {
    [$captcha, $session, $post] = $this->solvedCaptcha();

    $this->assertTrue($captcha->validate($post));
    $this->assertFalse($session->has('jdzcaptcha.1'));
  }

  public function testValidateRefusesAReplayedPost(): void
  {
    [$captcha, , $post] = $this->solvedCaptcha();
    $captcha->validate($post);

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, 4, 'Invalid Captcha ID');
  }

  public static function tamperedPostProvider(): array
  {
    return [
      'honeypot filled' => [['_jdzc-hf-hp' => 'http://spam.example'], 5, 'Invalid Captcha ID'],
      'honeypot field missing' => [['_jdzc-hf-hp' => null], 5, 'Invalid Captcha ID'],
      'id missing' => [['_jdzc-hf-id' => null], 4, 'Invalid Captcha ID'],
      'id not numeric' => [['_jdzc-hf-id' => 'one'], 4, 'Invalid Captcha ID'],
      'id of no puzzle' => [['_jdzc-hf-id' => '2'], 4, 'Invalid Captcha ID'],
      'token forged' => [['_jdzc-token' => 'forged'], 6, 'Invalid Captcha Token'],
      'token missing' => [['_jdzc-token' => null], 6, 'Invalid Captcha Token'],
      'selection empty' => [['_jdzc-hf-se' => ''], 2, 'No image was selected !'],
      'selection missing' => [['_jdzc-hf-se' => null], 2, 'No image was selected !'],
      'selection not a string' => [['_jdzc-hf-se' => ['160', '25', '320']], 2, 'No image was selected !'],
      'selection not x,y,width' => [['_jdzc-hf-se' => '160,25'], 1, "You've selected the wrong image."],
      'selection not numeric' => [['_jdzc-hf-se' => 'a,b,c'], 1, "You've selected the wrong image."],
      'selection with no width' => [['_jdzc-hf-se' => '0,0,0'], 1, "You've selected the wrong image."],
      'selection outside the image' => [['_jdzc-hf-se' => '500,25,320'], 1, "You've selected the wrong image."],
    ];
  }

  #[DataProvider('tamperedPostProvider')]
  public function testValidateRefusesATamperedPost(array $changes, int $code, string $message): void
  {
    [$captcha, , $post] = $this->solvedCaptcha();

    foreach ($changes as $field => $value) {
      if (null === $value) {
        unset($post[$field]);
      } else {
        $post[$field] = $value;
      }
    }

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, $code, $message);
  }

  public function testValidateRefusesTheWrongIcon(): void
  {
    [$captcha, $session, $post] = $this->solvedCaptcha();
    $post['_jdzc-hf-se'] = $this->selection($session, 1, false);

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, 1, "You've selected the wrong image.");
  }

  public function testValidateRefusesAPuzzleThatWasNeverAnswered(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();
    $captcha->getCaptchaData('light', 1);
    $post = [
      '_jdzc-hf-id' => '1',
      '_jdzc-hf-hp' => '',
      '_jdzc-token' => $session->get('jdzcaptcha.csrf'),
      '_jdzc-hf-se' => $this->selection($session, 1, true),
    ];

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, 1, "You've selected the wrong image.");
  }

  public function testValidateRefusesWhenTheLastAnswerWasWrong(): void
  {
    [$captcha, $session, $post] = $this->solvedCaptcha();
    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, 1, "You've selected the wrong image.");
  }

  public function testValidateRefusesWhenTheSessionHasNoToken(): void
  {
    [$captcha, $session, $post] = $this->solvedCaptcha();
    $session->remove('jdzcaptcha.csrf');
    unset($post['_jdzc-token']);

    $this->assertThrows(fn() => $captcha->validate($post), CaptchaValidationException::class, 6, 'Invalid Captcha Token');
  }

  /**
   * The lock used to be checked only when a new puzzle was asked for: a bot that
   * kept answering the same puzzle could click every slot, and its correct answer
   * lifted the lock.
   */
  public function testAnswersAreRefusedWhileLockedOut(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha(['attempts.amount' => 2, 'attempts.timeout' => 60]);
    $captcha->getCaptchaData('light', 1);
    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));
    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));
    $locked = $session->get('jdzcaptcha.1');

    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, true)));
    $this->assertSame($locked, $session->get('jdzcaptcha.1'), 'a refused answer changes nothing');
  }

  /**
   * Past the limit every wrong answer locks again: the lock used to be set only
   * when the count hit the limit exactly, so once it ran out the same puzzle could
   * be answered without limit.
   */
  public function testAWrongAnswerAfterTheLockRanOutLocksAgain(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha(['attempts.amount' => 2, 'attempts.timeout' => 60]);
    $captcha->getCaptchaData('light', 1);
    $captcha->setSelectedAnswer($this->answer($session, 1, false));
    $captcha->setSelectedAnswer($this->answer($session, 1, false));

    $expired = $session->get('jdzcaptcha.1');
    $expired['attemptsTimeout'] = time() - 1;
    $session->set('jdzcaptcha.1', $expired);

    $before = time();
    $this->assertFalse($captcha->setSelectedAnswer($this->answer($session, 1, false)));

    $relocked = $session->get('jdzcaptcha.1');
    $this->assertSame(3, $relocked['attempts']);
    $this->assertGreaterThanOrEqual($before + 60, $relocked['attemptsTimeout']);
  }

  /**
   * x = 0 is the left edge of the first icon: it used to resolve to slot 0, which
   * does not exist.
   */
  public function testTheLeftEdgeOfTheImageIsTheFirstIcon(): void
  {
    [$captcha, $session, $post] = $this->solvedCaptcha();
    $puzzle = $session->get('jdzcaptcha.1');
    $puzzle['icons'][1] = $puzzle['correctId'];
    $session->set('jdzcaptcha.1', $puzzle);
    $post['_jdzc-hf-se'] = '0,25,320';

    $this->assertTrue($captcha->validate($post));
  }

  public static function malformedClickProvider(): array
  {
    return [
      'x not numeric: refused, not counted' => [['x' => 'a'], 0],
      'width not numeric: refused, not counted' => [['w' => 'wide'], 0],
      'id not numeric: refused, not counted' => [['i' => 'one'], 0],
      'no width: a wrong answer' => [['w' => 0], 1],
      'right of the image: a wrong answer' => [['x' => 500], 1],
      'below the image: a wrong answer' => [['y' => 80], 1],
    ];
  }

  /**
   * A click the widget cannot produce used to end in a TypeError, a division by
   * zero or an undefined array key.
   */
  #[DataProvider('malformedClickProvider')]
  public function testAMalformedClickIsRefusedWithoutAnError(array $changes, int $attempts): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();
    $captcha->getCaptchaData('light', 1);

    $this->assertFalse($captcha->setSelectedAnswer(array_merge($this->answer($session, 1, true), $changes)));
    $this->assertSame($attempts, $session->get('jdzcaptcha.1')['attempts'] ?? 0);
  }

  public function testAnAnswerForAPuzzleThatDoesNotExistIsRefused(): void
  {
    [$captcha, $session] = $this->createInitializedCaptcha();

    $this->assertFalse($captcha->setSelectedAnswer(['i' => 77, 'x' => 10, 'y' => 25, 'w' => 320]));
    $this->assertFalse($session->has('jdzcaptcha.77'));
  }

  public static function nameSpaceProvider(): array
  {
    return [
      'a slash' => ['app/captcha', 'app/captchaX9'],
      'a dot' => ['my.captcha', 'myXcaptcha.9'],
    ];
  }

  /**
   * The namespace used to go into the cleaning regex unquoted: a slash broke the
   * pattern (warning, nothing cleaned), a dot matched the app's own keys.
   */
  #[DataProvider('nameSpaceProvider')]
  public function testInitCleansOnlyItsOwnNamespace(string $nameSpace, string $appKey): void
  {
    [$captcha, $session] = $this->createCaptcha(['nameSpace' => $nameSpace]);
    $session->set($nameSpace . '.7', ['correctId' => 3]);
    $session->set($appKey, ['id' => 12]);

    $captcha->init();

    $this->assertFalse($session->has($nameSpace . '.7'));
    $this->assertTrue($session->has($appKey));
  }
}
