<?php

namespace JDZ\Captcha\Tests;

use JDZ\Captcha\CaptchaData;
use PHPUnit\Framework\TestCase;

class CaptchaDataTest extends TestCase
{
  public function testConstructorWithEmptyData(): void
  {
    $data = new CaptchaData(42);

    $this->assertEquals(42, $data->id);
    $this->assertEquals(0, $data->get('correctId'));
    $this->assertFalse($data->get('requested'));
    $this->assertFalse($data->get('completed'));
    $this->assertEquals(0, $data->get('attempts'));
    $this->assertNotEmpty($data->get('ts'));
  }

  public function testConstructorWithExistingData(): void
  {
    $sessionData = [
      'mode' => 'light',
      'icons' => [1 => 5, 2 => 3, 3 => 5],
      'iconIds' => [5, 3],
      'correctId' => 3,
      'requested' => true,
      'completed' => false,
      'attempts' => 1,
      'ts' => time(),
    ];

    $data = new CaptchaData(7, $sessionData);

    $this->assertEquals(7, $data->id);
    $this->assertEquals(3, $data->get('correctId'));
    $this->assertTrue($data->get('requested'));
    $this->assertEquals([5, 3], $data->get('iconIds'));
  }

  public function testClear(): void
  {
    $data = new CaptchaData(1, [
      'correctId' => 5,
      'requested' => true,
      'completed' => true,
      'attempts' => 3,
      'ts' => time() - 3600,
    ]);

    $data->clear();

    $this->assertEquals(0, $data->get('correctId'));
    $this->assertFalse($data->get('requested'));
    $this->assertFalse($data->get('completed'));
    $this->assertEquals(0, $data->get('attempts'));
    // ts should be reset to current time
    $this->assertEqualsWithDelta(time(), $data->get('ts'), 2);
  }

  public function testIsExpiredReturnsFalseForRecent(): void
  {
    $data = new CaptchaData(1, ['ts' => time()]);
    $this->assertFalse($data->isExpired());
  }

  public function testIsExpiredReturnsTrueForOld(): void
  {
    $data = new CaptchaData(1, ['ts' => time() - (31 * 60)]); // 31 minutes ago
    $this->assertTrue($data->isExpired());
  }
}
