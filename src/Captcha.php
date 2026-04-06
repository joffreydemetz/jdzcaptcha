<?php

namespace JDZ\Captcha;

use JDZ\Captcha\Exception\CaptchaException;
use JDZ\Captcha\Exception\CaptchaValidationException;
use JDZ\Captcha\Session\CaptchaSessionInterface;
use JDZ\Captcha\Session\NativeSession;

class Captcha
{
  public CaptchaConfig $config;
  public CaptchaToken $token;
  public CaptchaData $data;

  private CaptchaSessionInterface $session;
  private string $nS;

  public function __construct(CaptchaConfig $config, ?CaptchaSessionInterface $session = null)
  {
    $this->config = $config;
    $this->session = $session ?? new NativeSession();
    $this->nS = $this->config->get('nameSpace');
  }

  public function init(): static
  {
    $this->token = new CaptchaToken(
      $this->config->getBool('token'),
      $this->session->get($this->nS . '.csrf')
    );

    $this->session->set($this->nS . '.csrf', $this->token->make());

    $this->cleanSessionData();
    $this->checkIconSet();

    return $this;
  }

  public function getToken(): CaptchaToken
  {
    return $this->token;
  }

  public function getJsConfig(): array
  {
    return $this->config->getJsConfig();
  }

  /**
   * Initializes the state of a captcha. The amount of icons shown in the captcha image, their positions,
   * which icon is correct and which icon identifiers should be used will all be determined in this function.
   * This information will be stored in the session. The details required to initialize the client
   * will be returned as a base64 encoded JSON string.
   *
   * In case a timeout is detected, no state will be initialized and an error message
   * will be returned, also as a base64 encoded JSON string.
   */
  public function getCaptchaData(string $theme, int $identifier): string
  {
    $this->createCaptchaData($identifier);

    // Check if the max attempts limit has been reached and a timeout is active.
    if ($tOut = $this->data->get('attemptsTimeout')) {
      if (time() <= $tOut) {
        return base64_encode(json_encode([
          'error' => 1,
          'data' => ($tOut - time()) * 1000
        ]));
      }

      $this->data->set('attemptsTimeout', 0);
      $this->data->set('attempts', 0);
    }

    $minIconAmount = $this->config->getInt('image.amount.min');
    $maxIconAmount = $this->config->getInt('image.amount.max');

    // Determine the number of icons to add to the image.
    $iconAmount = $minIconAmount;
    if ($minIconAmount <> $maxIconAmount) {
      $iconAmount = mt_rand($minIconAmount, $maxIconAmount);
    }

    // Number of times the correct image will be placed onto the placeholder.
    $correctIconAmount = mt_rand(1, $this->config->getArray('sizes')[$iconAmount]['correctMax']);
    $totalIconAmount = $this->calculateIconAmounts($iconAmount, $correctIconAmount);
    $totalIconAmount[] = $correctIconAmount;

    // Icon position and ID information.
    $iconPositions = [];
    $iconIds = [];
    $correctIconId = -1;

    // Create a random 'icon position' order.
    $tempPositions = range(1, $iconAmount);
    shuffle($tempPositions);

    // Generate the icon positions/IDs array.
    $i = 0;
    while (count($iconIds) < count($totalIconAmount)) {
      $tempIconId = mt_rand(1, $this->config->getInt('nbIcons'));
      if (!in_array($tempIconId, $iconIds)) {
        $iconIds[] = $tempIconId;

        for ($j = 0; $j < $totalIconAmount[$i]; $j++) {
          $tempKey = array_pop($tempPositions);
          $iconPositions[$tempKey] = $tempIconId;
        }

        if ($correctIconId === -1 && min($totalIconAmount) === $totalIconAmount[$i]) {
          $correctIconId = $tempIconId;
        }

        $i++;
      }
    }

    $attemptsCount = $this->data->get('attempts');

    $this->data->clear();

    $this->data->sets([
      'mode' => $theme,
      'icons' => $iconPositions,
      'iconIds' => $iconIds,
      'correctId' => $correctIconId,
      'requested' => false,
      'attempts' => $attemptsCount,
    ]);
    $this->session->set($this->nS . '.' . $this->data->id, $this->data->all());

    return base64_encode(json_encode(['id' => $identifier]));
  }

  public function validate(array $post): bool
  {
    $fields = $this->config->getArray('fields');

    if (empty($post)) {
      throw new CaptchaValidationException($this->config->get('messages.empty_form'), 3);
    }

    if (!isset($post[$fields['id']]) || !is_numeric($post[$fields['id']])) {
      throw new CaptchaValidationException($this->config->get('messages.invalid_id'), 4);
    }

    if (!isset($post[$fields['honeypot']]) || !empty($post[$fields['honeypot']])) {
      throw new CaptchaValidationException($this->config->get('messages.invalid_id'), 5);
    }

    $token = $post[$fields['token']] ?? null;

    if (!$this->validateToken($token)) {
      throw new CaptchaValidationException($this->config->get('messages.form_token'), 6);
    }

    $identifier = $post[$fields['id']];

    if (false === $this->session->has($this->nS . '.' . $identifier)) {
      throw new CaptchaValidationException($this->config->get('messages.invalid_id'), 4);
    }

    $this->createCaptchaData($identifier);

    if (empty($post[$fields['selection']]) || !is_string($post[$fields['selection']])) {
      throw new CaptchaValidationException($this->config->get('messages.no_selection'), 2);
    }

    $icons = $this->data->get('icons');

    $selection = explode(',', $post[$fields['selection']]);
    if (count($selection) === 3) {
      $clickedPosition = $this->determineClickedIcon($selection[0], $selection[1], $selection[2], count($icons));
    }

    if (false === $this->data->get('completed') || !isset($clickedPosition) || $icons[$clickedPosition] !== $this->data->get('correctId')) {
      throw new CaptchaValidationException($this->config->get('messages.wrong_icon'), 1);
    }

    $this->session->remove($this->nS . '.' . $identifier);

    return true;
  }

  public function setSelectedAnswer(array $payload): bool
  {
    if (empty($payload)) {
      return false;
    }

    if (!isset($payload['i']) || !isset($payload['x']) || !isset($payload['y']) || !isset($payload['w'])) {
      return false;
    }

    $this->createCaptchaData($payload['i']);

    $icons = $this->data->get('icons');
    $clickedPosition = $this->determineClickedIcon($payload['x'], $payload['y'], $payload['w'], count($icons));

    if ($icons[$clickedPosition] === $this->data->get('correctId')) {
      $this->data->sets([
        'attempts' => 0,
        'attemptsTimeout' => 0,
        'completed' => true,
      ]);

      $this->session->set($this->nS . '.' . $this->data->id, $this->data->all());
      return true;
    }

    $this->data->set('completed', false);

    $attempts = $this->data->get('attempts') + 1;
    $this->data->set('attempts', $attempts);

    if ($attempts === $this->config->getInt('attempts.amount') && $this->config->getInt('attempts.timeout') > 0) {
      $this->data->set('attemptsTimeout', time() + $this->config->getInt('attempts.timeout'));
    }

    $this->session->set($this->nS . '.' . $this->data->id, $this->data->all());
    return false;
  }

  /**
   * Returns a GD image containing the captcha icons, or false on failure.
   * The consuming app is responsible for outputting the image (headers, imagepng, etc.).
   *
   * @throws CaptchaException If the image was already requested (prevents replay).
   */
  public function getImage(int $identifier): \GdImage|false
  {
    $this->createCaptchaData($identifier);

    if (true === $this->data->get('requested')) {
      throw new CaptchaException('Image already requested', 403);
    }

    if (!$this->data->get('correctId')) {
      throw new CaptchaException('Invalid captcha state', 403);
    }

    $this->data->set('requested', true);
    $this->session->set($this->nS . '.' . $this->data->id, $this->data->all());

    return $this->generateImage();
  }

  /**
   * Invalidates a captcha session (e.g. when interaction time expired).
   */
  public function invalidate(int $identifier): void
  {
    $this->session->remove($this->nS . '.' . $identifier);
  }

  /**
   * Validates the CSRF token.
   */
  public function validateToken(?string $payloadToken, ?string $headerToken = null): bool
  {
    if (true === $this->config->getBool('token')) {
      $sessionToken = $this->session->get($this->nS . '.csrf');

      if (empty($sessionToken)) {
        return false;
      }

      if (null !== $headerToken) {
        return $sessionToken === $payloadToken && $sessionToken === $headerToken;
      }

      return $sessionToken === $payloadToken;
    }

    return true;
  }

  /**
   * Returns a generated image containing the icons for the current captcha instance.
   */
  public function generateImage(): \GdImage|false
  {
    $placeholderPath = realpath($this->config->get('placeholder'));
    $iconPath = $this->config->getIconSetPath();

    $placeholder = imagecreatefrompng($placeholderPath);

    $iconImages = [];
    foreach ($this->data->get('iconIds') as $id) {
      $iconImages[$id] = imagecreatefrompng(realpath($iconPath . '/icon-' . $id . '.png'));
    }

    $iconCount = count($this->data->get('icons'));
    $iconSize = $this->config->getArray('sizes')[$iconCount]['size'];
    $iconOffset = (int)((($this->config->getInt('container.width') / $iconCount) - 30) / 2);
    $iconOffsetAdd = (int)(($this->config->getInt('container.width') / $iconCount) - $iconSize);
    $iconLineSize = (int)($this->config->getInt('container.width') / $iconCount);

    $rotateEnabled = $this->config->getBool('image.rotate');
    $flipHorizontally = $this->config->getBool('image.flip.horizontally');
    $flipVertically = $this->config->getBool('image.flip.vertically');
    $borderEnabled = $this->config->getBool('image.border');

    if ($borderEnabled) {
      try {
        $color = $this->config->getArray('variants.' . $this->data->get('mode'))['border'];
      } catch (\Exception $e) {
        $color = $this->config->getArray('container.border');
      }

      if (count($color) <> 3) {
        $color = [240, 240, 240];
      }

      $borderColor = imagecolorallocate($placeholder, $color[0], $color[1], $color[2]);
    }

    $xOffset = $iconOffset;
    for ($i = 0; $i < $iconCount; $i++) {
      $icon = $iconImages[$this->data->get('icons')[$i + 1]];

      if ($rotateEnabled) {
        $degree = mt_rand(1, 4);
        if ($degree !== 4) {
          $icon = imagerotate($icon, $degree * 90, 0);
        }
      }

      if ($flipHorizontally && mt_rand(1, 2) === 1) {
        imageflip($icon, \IMG_FLIP_HORIZONTAL);
      }

      if ($flipVertically && mt_rand(1, 2) === 1) {
        imageflip($icon, \IMG_FLIP_VERTICAL);
      }

      imagecopy($placeholder, $icon, ($iconSize * $i) + $xOffset, 10, 0, 0, 30, 30);
      $xOffset += $iconOffsetAdd;

      if ($borderEnabled && $i > 0) {
        imageline($placeholder, $iconLineSize * $i, 0, $iconLineSize * $i, 50, $borderColor);
      }
    }

    return $placeholder;
  }

  /**
   * Removes expired captcha session data.
   */
  protected function cleanSessionData(): void
  {
    $sessionData = $this->session->all();

    foreach ($sessionData as $key => $value) {
      if (!preg_match("/^" . $this->nS . "\.(.+)$/", $key, $m)) {
        continue;
      }

      if ('csrf' === $m[1]) {
        continue;
      }

      $identifier = (int)$m[1];

      if (empty($value['ts'])) {
        $this->session->remove($key);
        continue;
      }

      $data = new CaptchaData($identifier, $value);
      if (true === $data->isExpired()) {
        $this->session->remove($key);
      }
    }
  }

  /**
   * Validates the icon set directory and counts available icons.
   */
  protected function checkIconSet(): void
  {
    $path = $this->config->getIconSetPath();

    if (!$path || !is_dir($path)) {
      throw new CaptchaException('JdzCaptcha icon set not found in ' . $this->config->getIconSetPath(false));
    }

    $fi = new \FilesystemIterator($path . '/', \FilesystemIterator::SKIP_DOTS);
    $count = \iterator_count($fi);

    if ($count < 10) {
      throw new CaptchaException('JdzCaptcha icon set has less than 10 icons in ' . $this->config->getIconSetPath(false));
    }

    $this->config->set('nbIcons', $count);
  }

  protected function createCaptchaData(int $identifier = 0): void
  {
    $this->data = new CaptchaData($identifier, $this->session->get($this->nS . '.' . $identifier, []));
  }

  protected function determineClickedIcon(int $clickedXPos, int $clickedYPos, int $captchaWidth, int $iconAmount): int
  {
    if ($clickedXPos < 0 || $clickedXPos > $captchaWidth || $clickedYPos < 0 || $clickedYPos > 50) {
      return -1;
    }
    return (int)ceil($clickedXPos / ($captchaWidth / $iconAmount));
  }

  protected function calculateIconAmounts(int $iconCount, int $smallestIconCount = 1): array
  {
    $remainder = $iconCount - $smallestIconCount;
    $remainderDivided = $remainder / 2;
    $pickDivided = mt_rand(1, 2) === 1;

    if (fmod($remainderDivided, 1) !== 0.0 && $pickDivided) {
      $left = floor($remainderDivided);
      $right = ceil($remainderDivided);

      if ($left > $smallestIconCount && $right > $smallestIconCount) {
        return [$left, $right];
      }
    } elseif ($pickDivided === true && $remainderDivided > $smallestIconCount) {
      return [$remainderDivided, $remainderDivided];
    }

    return [$remainder];
  }
}
