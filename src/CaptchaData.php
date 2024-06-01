<?php
namespace Captcha;

class CaptchaData
{
  public int $id;
  public array $data = [];
  protected int $validForMinutes = 30;
  
  public function __construct(int $id, array $data=[])
  {
    $this->id = $id;
    
    if ( $data ){
      $this->data = $data;      
    }
    else {
      $this->clear();
    }
  }
  
  public function isExpired(): bool
  {
    $from_time = $this->get('ts');
    $to_time = time();
    $diff_minutes = round(abs($from_time - $to_time) / 60, 2);
    
    if ( $diff_minutes >= $this->validForMinutes ){
      return true;
    }
    
    return false;
  }
  
  public function get(string $key, mixed $default=null): mixed
  {
    return isset($this->data[$key]) ? $this->data[$key] : $default;
  }
  
  public function set(string $key, mixed $value)
  {
    $this->data[$key] = $value;
    return $this;
  }
  
  public function sets(array $data)
  {
    $this->data = array_merge($this->data, $data);
    return $this;
  }
  
  public function all(): array
  {
    return $this->data;
  }
  
  public function clear()
  {
    $this->data['icons'] = [];
    $this->data['iconIds'] = [];
    $this->data['correctId'] = 0;
    $this->data['requested'] = false;
    $this->data['completed'] = false;
    $this->data['attempts'] = 0;
    $this->data['ts'] = time();
    
    return $this;
  }
}
