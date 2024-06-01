<?php 
namespace Captcha;

/**
 * Config
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class CaptchaConfig 
{
  const DEFAULTS = [
    'debug' => false,
    'lang' => 'en',
    'token' => true,
    'nameSpace' => 'jdzcaptcha',
    'iconPath' => '',
    'iconSet' => 'streamline',
    'iconsVariant' => 'light',
    'nbIcons' => 0,
    'path' => '/captcha/request/',
    'loaderPath' => '/captcha/load/',
    'fontFamily' => '',
    'credits' => 'show',
    'messages' => [
      'wrong_icon' => 'You’ve selected the wrong image.',
      'no_selection' => 'No image was selected !',
      'empty_form' => 'Form is empty',
      'invalid_id' => 'Invalid Captcha ID',
      'form_token' => 'Invalid Captcha Token',
      'initialization' => [
        'verify' => 'Verify that you are human.',
        'loading' => 'Loading challenge...',
      ],
      'header' => 'Select the image displayed the <u>least</u> amount of times',
      'correct' => 'Verification complete.',
      'incorrect' => [
        'title' => 'Uh oh.',
        'subtitle' => 'You’ve selected the wrong image.',
      ],
      'timeout' => [
        'title' => 'Please wait 60 sec.',
        'subtitle' => 'You made too many incorrect selections.',
      ],
    ],
    'container' => [
      'width' => 320,
    ],
    'image' => [
      'rotate' => true,
      'border' => true,
      'amount' => [
        'min' => 5,
        'max' => 8,
      ],
      'flip' => [
        'horizontally' => true,
        'vertically' => true,
      ],
    ],
    'attempts' => [
      'amount' => 3,
      'timeout' => 60,
    ],
    'security' => [
      'clickDelay' => 500,
      'hoverDetection' => true,
      'enableInitialMessage' => true,
      'initializeDelay' => 500,
      'selectionResetDelay' => 3000,
      'loadingAnimationDelay' => 1000,
      'invalidateTime' => 12000, // 1000 * 60 * 2
    ],
    'fields' => [
      'selection' => '_jdzc-hf-se',
      'id' => '_jdzc-hf-id',
      'honeypot' => '_jdzc-hf-hp',
      'token' => '_jdzc-token',
    ],
  ];
  
  protected array $data = [];
  // protected array $data = self::DEFAULTS;
  
  public function loadFromFile(string $path)
  {
    try {
      $data = (array) \Symfony\Component\Yaml\Yaml::parseFile($path);
      $this->setVars( $this->toDot($data) );
    }
    catch(\Symfony\Component\Yaml\Exception\ParseException $e){
      throw new \Exception('Unable to parse the YAML application config : '.$e->getMessage());
    }
    
    return $this;
  }
  
  public function set(string $path, $value)
  {
    if ( $nodes = explode('.', $path) ){
      $node =& $this->data;
      
      for($i=0, $n=count($nodes)-1; $i<$n; $i++){
        if ( !isset($node[$nodes[$i]]) ){
          $node[$nodes[$i]] = [];
        }
        $node =& $node[$nodes[$i]];
      }
      
      $node[$nodes[$i]] = $value;
    }
    else {
      $node[$path] = $value;
    }
    
    return $this;
  }
  
  public function getDefault(string $path)
  {
    $result = null;
    
    $node = self::DEFAULTS;
    
    if ( strpos($path, '.') ){
      $nodes = explode('.', $path);
      
      for($i=0, $n=count($nodes)-1; $i<$n; $i++){
        if ( !isset($node[$nodes[$i]]) ){
          $node = null;
          break;
        }
        
        $node = $node[$nodes[$i]];
      }
      
      if ( $node && isset($node[$nodes[$i]]) ){
        $result = $node[$nodes[$i]];
      }
    }
    else {
      if ( isset($node[$path]) ){
        $result = $node[$path];
      }
    }
    
    if ( !isset($result) ){
      $result = $default;
    }
    
    return $result;
  }
  
  public function get(string $path, $default=null)
  {
    $result = null;
    
    $node = $this->data;
    
    if ( strpos($path, '.') ){
      $nodes = explode('.', $path);
      
      for($i=0, $n=count($nodes)-1; $i<$n; $i++){
        if ( !isset($node[$nodes[$i]]) ){
          $node = null;
          break;
        }
        
        $node = $node[$nodes[$i]];
      }
      
      if ( $node && isset($node[$nodes[$i]]) ){
        $result = $node[$nodes[$i]];
      }
    }
    else {
      if ( isset($node[$path]) ){
        $result = $node[$path];
      }
    }
    
    if ( !isset($result) ){
      $result = $default;
    }
    
    return $result;
  }
  
  public function getBool(string $path, bool $default=false): bool
  {
    if ( null === ($result=$this->get($path, null)) ){
      return $default;
    }
    
    return true === $result || 1 === intval($result);
  }
  
  public function getInt(string $path, int $default=0): int
  {
    $result = $this->get($path, null);
    
    if ( null === $result ){
      return $default;
    }
    
    return intval($result);
  }
  
  public function getArray(string $path, array $default=[]): array
  {
    if ( null === ($result=$this->get($path, null)) ){
      return $default;
    }
    
    return (array)$result;
  }
  
  public function def(string $path, mixed $default=null)
  {
    $value = $this->get($path, (string) $default);
    $this->set($path, $value);
    return $this;
  }
  
  public function has(string $path): bool
  {
    $node = $this->data;
    
    if ( strpos($path, '.') ){
      $nodes = explode('.', $path);
      
      for($i=0, $n=count($nodes)-1; $i<$n; $i++){
        if ( !isset($node[$nodes[$i]]) ){
          $node = null;
          break;
        }
        
        $node = $node[$nodes[$i]];
      }
      
      if ( $node && isset($node[$nodes[$i]]) ){
        return true;
      }
    }
    else {
      if ( isset($node[$path]) ){
        return true;
      }
    }
    
    return false;
  }
  
  public function erase(string $path)
  {
    $node = $this->data;
    
    if ( strpos($path, '.') ){
      $nodes = explode('.', $path);
      
      for($i=0, $n=count($nodes)-1; $i<$n; $i++){
        if ( !isset($node[$nodes[$i]]) ){
          $node = null;
          break;
        }
        
        $node = $node[$nodes[$i]];
      }
      
      if ( $node && isset($node[$nodes[$i]]) ){
        unset($node[$nodes[$i]]);
      }
    }
    else {
      if ( isset($node[$path]) ){
        unset($node[$path]);
      }
    }
    
    return $this;
  }
  
  public function all(): array
  {
    return $this->data;
  }
  
  public function toDot(array $data): array
  {
    $recurseIter = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
    
    $result = [];
    
    foreach($recurseIter as $leafValue){
        $keys = [];
        foreach(range(0, $recurseIter->getDepth()) as $depth){
          $keys[] = $recurseIter->getSubIterator($depth)->key();
        }
        $result[join('.', $keys)] = $leafValue;
    }
    
    return $result;
  }
  
  public function setData(array $data)
  {
    return $this->setVars($data);
  }
  
  public function setVars(array $data)
  {
    foreach($data as $key => $value){
      $this->set($key, $value);
    }
    return $this;
  }
  
  public function getJsConfig(): array
  {
    $config = [
      // 'debug' => $this->getBool('debug', $this->getDefault('debug')),
      // 'lang' => $this->get('lang', $this->getDefault('lang')),
      'token' => $this->getBool('token', $this->getDefault('token')),
      'path' => $this->get('path', $this->getDefault('path')),
      // 'loader' => $this->get('loaderPath', $this->getDefault('loaderPath')),
      'fontFamily' => $this->get('fontFamily', $this->getDefault('fontFamily')),
      'iconsVariant' => $this->get('iconsVariant', $this->getDefault('iconsVariant')),
      'credits' => $this->get('credits', $this->getDefault('credits')),
      'security' => [
        'clickDelay' => $this->getInt('security.clickDelay', $this->getDefault('security.clickDelay')),
        'hoverDetection' => $this->getBool('security.hoverDetection', $this->getDefault('security.hoverDetection')),
        'enableInitialMessage' => $this->getBool('security.enableInitialMessage', $this->getDefault('security.enableInitialMessage')),
        'initializeDelay' => $this->getInt('security.initializeDelay', $this->getDefault('security.initializeDelay')),
        'selectionResetDelay' => $this->getInt('security.selectionResetDelay', $this->getDefault('security.selectionResetDelay')),
        'loadingAnimationDelay' => $this->getInt('security.loadingAnimationDelay', $this->getDefault('security.loadingAnimationDelay')),
        'invalidateTime' => $this->getInt('security.invalidateTime', $this->getDefault('security.invalidateTime')),
      ],
      'fields' => [
        'selection' => $this->get('fields.selection', $this->getDefault('fields.selection')),
        'id' => $this->get('fields.id', $this->getDefault('fields.id')),
        'honeypot' => $this->get('fields.honeypot', $this->getDefault('fields.honeypot')),
        'token' => $this->get('fields.token', $this->getDefault('fields.token')),
      ],
      'messages' => [
        'initialization' => [
          'verify' => $this->get('messages.initialization.verify', $this->getDefault('messages.initialization.verify')),
          'loading' => $this->get('messages.initialization.loading', $this->getDefault('messages.initialization.loading')),
        ],
        'header' => $this->get('messages.header', $this->getDefault('messages.header')),
        'correct' => $this->get('messages.correct', $this->getDefault('messages.correct')),
        'incorrect' => [
          'title' => $this->get('messages.incorrect.title', $this->getDefault('messages.incorrect.title')),
          'subtitle' => $this->get('messages.incorrect.subtitle', $this->getDefault('messages.incorrect.subtitle')),
        ],
        'timeout' => [
          'title' => $this->get('messages.timeout.title', $this->getDefault('messages.timeout.title')),
          'subtitle' => $this->get('messages.timeout.subtitle', $this->getDefault('messages.timeout.subtitle')),
        ],
      ],
    ];
    
    return $this->array_diff_assoc_recursive($config, self::DEFAULTS);
  }
  
  protected function array_diff_assoc_recursive(array $array1, array $array2): array
  {
    $difference = [];
    
    foreach($array1 as $key => $value){
      if ( is_array($value) ){
        if ( !isset($array2[$key]) || !is_array($array2[$key]) ){
          $difference[$key] = $value;
        } 
        else {
          $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
          if ( !empty($new_diff) ){
            $difference[$key] = $new_diff;
          }
        }
      } 
      elseif ( !array_key_exists($key, $array2) || $array2[$key] !== $value ){
        $difference[$key] = $value;
      }
    }
    
    return $difference;
  }
}
