<?php

namespace JDZ\Captcha;

use JDZ\Captcha\CaptchaApp;
use JDZ\Captcha\CaptchaData;
use JDZ\Captcha\Exception\CaptchaValidationException;

class Captcha
{
  public CaptchaApp $app;
  public CaptchaData $data;

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
    // If reached, return an error and the remaining time.
    if ($tOut = $this->data->get('attemptsTimeout')) {
      if (time() <= $tOut) {
        return base64_encode(json_encode([
          'error' => 1,
          'data' => ($tOut - time()) * 1000 // remaining time.
        ]));
      }

      $this->data->set('attemptsTimeout', 0);
      $this->data->set('attempts', 0);
    }

    $minIconAmount = $this->app->config->getInt('image.amount.min');
    $maxIconAmount = $this->app->config->getInt('image.amount.max');

    // Determine the number of icons to add to the image.
    $iconAmount = $minIconAmount;
    // if ( $minIconAmount !== $maxIconAmount ){
    if ($minIconAmount <> $maxIconAmount) {
      $iconAmount = mt_rand($minIconAmount, $maxIconAmount);
    }

    // Number of times the correct image will be placed onto the placeholder.
    // $sizes = $this->app->config->getArray('sizes');
    $correctIconAmount = mt_rand(1, $this->app->config->getArray('sizes')[$iconAmount]['correctMax']);
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
      // Generate a random icon ID. If it is not in use yet, process it.
      $tempIconId = mt_rand(1, $this->app->config->getInt('nbIcons'));
      if (!in_array($tempIconId, $iconIds)) {
        $iconIds[] = $tempIconId;

        // Assign the current icon ID to one or more positions.
        for ($j = 0; $j < $totalIconAmount[$i]; $j++) {
          $tempKey = array_pop($tempPositions);
          $iconPositions[$tempKey] = $tempIconId;
        }

        // Set the least appearing icon ID as the correct icon ID.
        if ($correctIconId === -1 && min($totalIconAmount) === $totalIconAmount[$i]) {
          $correctIconId = $tempIconId;
        }

        $i++;
      }
    }

    // Get the last attempts count to restore, after clearing the session.
    $attemptsCount = $this->data->get('attempts');

    // Unset the previous session data.
    $this->data->clear();

    // Set the chosen icons and position and reset the requested status.
    $this->data->sets([
      'mode' => $theme,
      'icons' => $iconPositions,
      'iconIds' => $iconIds,
      'correctId' => $correctIconId,
      'requested' => false,
      'attempts' => $attemptsCount,
    ]);
    // debug($this->data->all());
    $this->app->request->getSession()->set($this->app->nS . '.' . $this->data->id, $this->data->all());

    return base64_encode(json_encode(['id' => $identifier]));
  }

  public function validate(array $post): bool
  {
    $fields = $this->app->config->getArray('fields');

    if (empty($post)) {
      throw new CaptchaValidationException($this->app->config->get('messages.empty_form'), 3);
    }

    if (!isset($post[$fields['id']]) || !is_numeric($post[$fields['id']])) {
      throw new CaptchaValidationException($this->app->config->get('messages.invalid_id'), 4);
    }

    if (!isset($post[$fields['honeypot']]) || !empty($post[$fields['honeypot']])) {
      throw new CaptchaValidationException($this->app->config->get('messages.invalid_id'), 5);
    }

    $token = (isset($post[$fields['token']])) ? $post[$fields['token']] : null;

    if (!$this->validateToken($token)) {
      throw new CaptchaValidationException($this->app->config->get('messages.form_token'), 6);
    }

    $identifier = $post[$fields['id']];

    if (false === $this->app->request->getSession()->has($this->app->nS . '.' . $identifier)) {
      throw new CaptchaValidationException($this->app->config->get('messages.invalid_id'), 4);
    }

    $this->createCaptchaData($identifier);

    // Check if the selection field is set.
    if (empty($post[$fields['selection']]) || !is_string($post[$fields['selection']])) {
      throw new CaptchaValidationException($this->app->config->get('messages.no_selection'), 2);
    }

    $icons = $this->data->get('icons');

    // Parse the selection.
    $selection = explode(',', $post[$fields['selection']]);
    if (count($selection) === 3) {
      $clickedPosition = $this->determineClickedIcon($selection[0], $selection[1], $selection[2], count($icons));
    }

    // If the clicked position matches the stored position, the form can be submitted.
    if (false === $this->data->get('completed') || !isset($clickedPosition) || $icons[$clickedPosition] !== $this->data->get('correctId')) {
      throw new CaptchaValidationException($this->app->config->get('messages.wrong_icon'), 1);
    }

    // Invalidate the captcha to prevent resubmission of a form on the same captcha.
    $this->app->request->getSession()->remove($this->app->nS . '.' . $identifier);

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

    // Check if the selection is set and matches the position from the session.
    if ($icons[$clickedPosition] === $this->data->get('correctId')) {
      $this->data->sets([
        'attempts' => 0,
        'attemptsTimeout' => 0,
        'completed' => true,
      ]);

      $this->app->request->getSession()->set($this->app->nS . '.' . $this->data->id, $this->data->all());
      return true;
    }

    $this->data->set('completed', false);

    $attempts = $this->data->get('attempts');
    $attempts += 1;
    $this->data->set('attempts', $attempts);

    // If the max amount has been reached, set a timeout (if set).
    if ($attempts === $this->app->config->getInt('attempts.amount') && $this->app->config->getInt('attempts.timeout') > 0) {
      $this->data->set('attemptsTimeout',  time() + $this->app->config->getInt('attempts.timeout'));
    }

    $this->app->request->getSession()->set($this->app->nS . '.' . $this->data->id, $this->data->all());
    return false;
  }

  /**
   * Displays an image containing multiple icons in a random order for the current captcha instance, linked
   * to the given captcha identifier. Headers will be set to prevent caching of the image. In case the captcha
   * image was already requested once, a HTTP status '403 Forbidden' will be set and no image will be returned.
   *
   * The image will only be rendered once as a PNG, and be destroyed right after rendering.
   */
  public function getImage(?int $identifier = null)
  {
    // Check if the captcha id is set
    if (isset($identifier) && $identifier > -1) {
      // Initialize the session.
      $this->createCaptchaData($identifier);

      // Check the amount of times an icon has been requested
      if (true === $this->data->get('requested')) {
        header('HTTP/1.1 403 Forbidden');
        exit;
      }

      if (!$this->data->get('correctId')) {
        header('HTTP/1.1 403 Forbidden');
        exit;
      }

      $this->data->set('requested', true);
      $this->app->request->getSession()->set($this->app->nS . '.' . $this->data->id, $this->data->all());

      $placeholder = realpath($this->app->config->get('placeholder'));
      $iconPath = $this->app->config->get('iconPath') . '/';

      // Check if the placeholder icon exists.
      if (is_file($placeholder)) {
        // Generate the captcha image.
        $generatedImage = $this->generateImage($iconPath, $placeholder);
        return $generatedImage;
      }
    }

    return false;
  }

  /**
   * Returns a generated image containing the icons for the current captcha instance. The icons will be copied
   * onto a placeholder image, located at the $placeholderPath. The icons will be randomly rotated and flipped
   * based on the captcha options.
   *
   * @return false|\GdImage|resource The generated image.
   */
  public function generateImage(string $iconPath, string $placeholderPath): mixed
  {
    // Prepare the placeholder image.
    $placeholder = imagecreatefrompng($placeholderPath);

    // Prepare the icon images.
    $iconImages = [];
    foreach ($this->data->get('iconIds') as $id) {
      $iconImages[$id] = imagecreatefrompng(realpath($iconPath . 'icon-' . $id . '.png'));
    }

    // Image pixel information.
    $iconCount = count($this->data->get('icons'));
    $iconSize = $this->app->config->getArray('sizes')[$iconCount]['size'];
    $iconOffset = (int)((($this->app->config->getInt('container.width') / $iconCount) - 30) / 2);
    $iconOffsetAdd = (int)(($this->app->config->getInt('container.width') / $iconCount) - $iconSize);
    $iconLineSize = (int)($this->app->config->getInt('container.width') / $iconCount);

    // Options.
    $rotateEnabled = $this->app->config->getBool('image.rotate');
    $flipHorizontally = $this->app->config->getBool('image.flip.horizontally');
    $flipVertically = $this->app->config->getBool('image.flip.vertically');
    $borderEnabled = $this->app->config->getBool('image.border');

    // Create the border color, if enabled.
    if ($borderEnabled) {
      // Determine border color.
      try {
        $color = $this->app->config->getArray('variants.' . $this->data->get('mode'))['border'];
      } catch (\Exception $e) {
        $color = $this->app->config->getArray('container.border');
      }

      if (count($color) <> 3) {
        $color = [240, 240, 240];
      }

      $borderColor = imagecolorallocate($placeholder, $color[0], $color[1], $color[2]);
    }

    // Copy the icons onto the placeholder.
    $xOffset = $iconOffset;
    for ($i = 0; $i < $iconCount; $i++) {
      // Get the icon image from the array. Use position to get the icon ID.
      $icon = $iconImages[$this->data->get('icons')[$i + 1]];
      // debug($icon);

      // Rotate icon, if enabled.
      if ($rotateEnabled) {
        $degree = mt_rand(1, 4);
        if ($degree !== 4) { // Only if the 'degree' is not the same as what it would already be at.
          $icon = imagerotate($icon, $degree * 90, 0);
        }
      }

      // Flip icon horizontally, if enabled.
      if ($flipHorizontally && mt_rand(1, 2) === 1) {
        imageflip($icon, \IMG_FLIP_HORIZONTAL);
      }

      // Flip icon vertically, if enabled.
      if ($flipVertically && mt_rand(1, 2) === 1) {
        imageflip($icon, \IMG_FLIP_VERTICAL);
      }

      // Copy the icon onto the placeholder.
      imagecopy($placeholder, $icon, ($iconSize * $i) + $xOffset, 10, 0, 0, 30, 30);
      $xOffset += $iconOffsetAdd;

      // Add the vertical separator lines to the placeholder, if enabled.
      if ($borderEnabled && $i > 0) {
        imageline($placeholder, $iconLineSize * $i, 0, $iconLineSize * $i, 50, $borderColor);
      }
    }
    // debug($iconImages);

    return $placeholder;
  }

  /**
   * Tries to load/initialize a session with the given captcha identifier.
   * When an existing session is found, it's data will be loaded, else a new session will be created.
   */
  protected function createCaptchaData(int $identifier = 0)
  {
    $this->data = new CaptchaData($identifier, $this->app->request->getSession()->get($this->app->nS . '.' . $identifier, []));
  }

  /**
   * Validates the global captcha session token against the given payload token and sometimes against a header token
   * as well. All the given tokens must match the global captcha session token to pass the check. This function
   * will only validate the given tokens if the 'token' option is set to TRUE. If the 'token' option is set to anything
   * else other than TRUE, the check will be skipped.
   *
   * @param string $payloadToken The token string received via the HTTP request body.
   * @param string|null $headerToken The token string received via the HTTP request headers. This value is optional,
   * as not every request will contain custom HTTP headers and thus this token should be able to be skipped. Default
   * value is NULL. When the value is set to anything else other than NULL, the given value will be checked against
   * the captcha session token.
   */
  public function validateToken(string $payloadToken, ?string $headerToken = null): bool
  {
    // Only validate if the token option is enabled.
    if (true === $this->app->config->getBool('token')) {
      $sessionToken = $this->app->request->getSession()->get($this->app->nS . '.csrf');

      // If the token is empty but the option is enabled, the token was never requested.
      if (empty($sessionToken)) {
        return false;
      }

      // Validate the payload and header token (if set) against the session token.
      if (null !== $headerToken) {
        return $sessionToken === $payloadToken && $sessionToken === $headerToken;
      }

      return $sessionToken === $payloadToken;
    }

    return true;
  }

  /**
   * Returns the clicked icon position based on the X and Y position and the captcha width.
   *
   * @param $clickedXPos int The X position of the click.
   * @param $clickedYPos int The Y position of the click.
   * @param $captchaWidth int The width of the captcha.
   */
  protected function determineClickedIcon(int $clickedXPos, int $clickedYPos, int $captchaWidth, int $iconAmount): int
  {
    // Check if the clicked position is valid.
    if ($clickedXPos < 0 || $clickedXPos > $captchaWidth || $clickedYPos < 0 || $clickedYPos > 50) {
      return -1;
    }
    return (int)ceil($clickedXPos / ($captchaWidth / $iconAmount));
  }

  /**
   * Calculates the amount of times 1 or more other icons can be present in the captcha image besides the correct icon.
   * Each other icons should be at least present 1 time more than the correct icon. When calculating the icon
   * amount(s), the remainder of the calculation ($iconCount - $smallestIconCount) will be used.
   *
   * Example 1: When $smallestIconCount is 1, and the $iconCount is 8, the return value can be [3, 4].
   * Example 2: When $smallestIconCount is 2, and the $iconCount is 6, the return value can be [4]. This is because
   * dividing the remainder (4 / 2 = 2) is equal to the $smallestIconCount, which is not possible.
   * Example 3: When the $smallestIconCount is 2, and the $iconCount is 8, the return value will be [3, 3].
   *
   * @param int $iconCount The total amount of icons which will be present in the generated image.
   * @param int $smallestIconCount The amount of times the correct icon will be present in the generated image.
   * @return int[] The number of times an icon should be rendered onto the captcha image. Each value in the returned
   * array represents a new unique icon.
   */
  protected function calculateIconAmounts(int $iconCount, int $smallestIconCount = 1): array
  {
    $remainder = $iconCount - $smallestIconCount;
    $remainderDivided = $remainder / 2;
    $pickDivided = mt_rand(1, 2) === 1; // 50/50 chance.

    // If division leads to decimal.
    if (fmod($remainderDivided, 1) !== 0.0 && $pickDivided) {
      $left = floor($remainderDivided);
      $right = ceil($remainderDivided);

      // Only return the divided numbers if both are larger than the smallest number.
      if ($left > $smallestIconCount && $right > $smallestIconCount) {
        return [$left, $right];
      }
    } elseif ($pickDivided === true && $remainderDivided > $smallestIconCount) {
      // If no decimals: only return the divided numbers if it is larger than the smallest number.
      return [$remainderDivided, $remainderDivided];
    }

    // Return the whole remainder.
    return [$remainder];
  }
}
