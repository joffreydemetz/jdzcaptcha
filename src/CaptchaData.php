<?php

namespace JDZ\Captcha;

use JDZ\Utils\Data as jData;

class CaptchaData extends jData
{
  public int $id;
  protected int $validForMinutes = 30;

  public function __construct(int $id, array $data = [])
  {
    $this->id = $id;

    if ($data) {
      $this->sets($data);
    } else {
      $this->clear();
    }
  }

  public function isExpired(): bool
  {
    $from_time = $this->get('ts');
    $to_time = time();
    $diff_minutes = round(abs($from_time - $to_time) / 60, 2);

    if ($diff_minutes >= $this->validForMinutes) {
      return true;
    }

    return false;
  }

  public function clear()
  {
    $this->erase('icons');
    $this->erase('iconIds');
    $this->set('correctId', 0);
    $this->set('requested', false);
    $this->set('completed', false);
    $this->set('attempts', 0);
    $this->set('ts', time());

    return $this;
  }
}
