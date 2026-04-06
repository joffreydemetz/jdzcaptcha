<?php

namespace JDZ\Captcha;

use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Utils\Data as jData;
use Symfony\Component\Yaml\Yaml;

class CaptchaConfig extends jData
{
  private jData $defaults;

  public function __construct()
  {
    $configFilePath = realpath(__DIR__ . '/../assets/config.yml');

    $this->loadFromFile($configFilePath);
    $this->set('iconPath', __DIR__ . '/../assets/icons');
    $this->set('placeholder', __DIR__ . '/../assets/placeholder.png');

    $this->defaults = new jData();
    $this->defaults->sets($this->all());
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

  public function getIconSetPath(bool $resolve = true): string
  {
    $path = $this->get('iconPath')
      . '/' . $this->get('series')
      . '/' . $this->get('theme');

    return $resolve ? (realpath($path) ?: '') : $path;
  }

  public function check(): void
  {
    $iconSetPath = $this->getIconSetPath();
    if (!$iconSetPath || !is_dir($iconSetPath)) {
      throw new CaptchaException('Invalid iconPath: ' . $this->getIconSetPath(false));
    }

    $placeholder = realpath($this->get('placeholder'));
    if (!$placeholder || !is_file($placeholder)) {
      throw new CaptchaException('Invalid placeholder path: ' . $this->get('placeholder'));
    }
  }

  public function getJsConfig(): array
  {
    $jsConfigVars = [
      'path',
      'fontFamily',
      'series',
      'theme',
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
