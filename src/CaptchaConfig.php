<?php

namespace JDZ\Captcha;

use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Utils\Data as jData;
use Symfony\Component\Yaml\Yaml;

/**
 * Config
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class CaptchaConfig extends jData
{
  const DEFAULTS = [
    'debug' => false,
    'lang' => 'en',
    'token' => true,
    'nameSpace' => 'jdzcaptcha',
    'iconPath' => '',
    'placeholder' => '',
    'theme' => 'lc',
    'variant' => 'light',
    'nbIcons' => 0,
    'path' => '/captcha/request/',
    'loaderPath' => '/captcha/load/',
    'fontFamily' => '',
    'credits' => 'show',
    'messages.wrong_icon' => 'You’ve selected the wrong image.',
    'messages.no_selection' => 'No image was selected !',
    'messages.empty_form' => 'Form is empty',
    'messages.invalid_id' => 'Invalid Captcha ID',
    'messages.form_token' => 'Invalid Captcha Token',
    'messages.initialization.verify' => 'Verify that you are human.',
    'messages.initialization.loading' => 'Loading challenge...',
    'messages.header' => 'Select the image displayed the <u>least</u> amount of times',
    'messages.correct' => 'Verification complete.',
    'messages.incorrect.title' => 'Uh oh.',
    'messages.incorrect.subtitle' => 'You’ve selected the wrong image.',
    'messages.timeout.title' => 'Please wait 60 sec.',
    'messages.timeout.subtitle' => 'You made too many incorrect selections.',
    'container.width' => 320,
    'image.rotate' => true,
    'image.border' => true,
    'image.amount.min' => 5,
    'image.amount.max' => 8,
    'image.flip.horizontally' => true,
    'image.flip.vertically' => true,
    'attempts.amount' => 3,
    'attempts.timeout' => 60,
    'security.clickDelay' => 500,
    'security.hoverDetection' => true,
    'security.enableInitialMessage' => true,
    'security.initializeDelay' => 500,
    'security.selectionResetDelay' => 3000,
    'security.loadingAnimationDelay' => 1000,
    'security.invalidateTime' => 12000, // 1000 * 60 * 2
    'fields.selection' => '_jdzc-hf-se',
    'fields.id' => '_jdzc-hf-id',
    'fields.honeypot' => '_jdzc-hf-hp',
    'fields.token' => '_jdzc-token',
  ];

  private jData $defaults;

  public function __construct()
  {
    $this->defaults = new jData();
    $this->defaults->sets(self::DEFAULTS);
    $this->defaults->set('iconPath', dirname(__DIR__) . '/assets/icons/');
    $this->defaults->set('placeholder', dirname(__DIR__) . '/assets/placeholder.png');
    $this->sets(self::DEFAULTS);
  }

  public function loadFromFile(string $path)
  {
    try {
      $data = (array) Yaml::parseFile($path);
      $this->sets($this->toDot($data));
    } catch (\Throwable $e) {
      throw new CaptchaException('Unable to parse the YAML application config : ' . $e->getMessage());
    }

    return $this;
  }

  public function toDot(array $data): array
  {
    return $this->flatten($data);
  }

  public function getJsConfig(): array
  {
    $jsConfigVars = [
      'path',
      'fontFamily',
      'theme',
      'variant',
      'credits',
      'fields.selection',
      'fields.id',
      'fields.honeypot',
      'fields.token',
      'messages.initialization.verify',
      'messages.initialization.loading',
      'messages.header',
      'messages.correct',
      'messages.incorrect.title',
      'messages.incorrect.subtitle',
      'messages.timeout.title',
      'messages.timeout.subtitle',
      // -- integers
      'security.clickDelay',
      'security.initializeDelay',
      'security.selectionResetDelay',
      'security.loadingAnimationDelay',
      'security.invalidateTime',
      // -- booleans
      'token',
      'security.hoverDetection',
      'security.enableInitialMessage',
    ];

    $jsConfig = new jData();
    foreach ($jsConfigVars as $var) {
      if ($this->get($var) !== $this->defaults->get($var)) {
        $jsConfig->set($var, $this->get($var));
      }
    }

    return $jsConfig->all();
  }
}
