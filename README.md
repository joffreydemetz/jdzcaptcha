# JDZ Captcha

A PHP icon-based CAPTCHA library. Instead of distorted text, users identify and select the image displayed the least number of times in a randomized grid of icons.

## Requirements

- PHP >= 8.2
- GD extension (`ext-gd`)

## Installation

```bash
composer require jdz/jdzcaptcha
```

## Quick Start

```php
use JDZ\Captcha\Captcha;
use JDZ\Captcha\CaptchaConfig;

$config = new CaptchaConfig();
$captcha = new Captcha($config);
$captcha->init();

// Generate captcha data (returns base64-encoded JSON)
$data = $captcha->getCaptchaData('light', 1);

// Generate the image
$image = $captcha->getImage(1);
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
```

### Handling User Selection (AJAX)

```php
$result = $captcha->setSelectedAnswer([
    'i' => 1,     // captcha identifier
    'x' => 100,   // click x position
    'y' => 25,    // click y position
    'w' => 320,   // container width
]);
// Returns true if the correct icon was selected
```

### Form Validation

```php
use JDZ\Captcha\Exception\CaptchaValidationException;

try {
    $captcha->validate($_POST);
    // Captcha passed
} catch (CaptchaValidationException $e) {
    // Captcha failed
    echo $e->getMessage();
}
```

## Session Adapters

The library ships with three session adapters:

**Native PHP session** (default):
```php
$captcha = new Captcha($config); // uses NativeSession automatically
```

**Symfony**:
```php
use JDZ\Captcha\Session\SymfonySession;

$adapter = new SymfonySession($symfonySession);
$captcha = new Captcha($config, $adapter);
```

**PSR-7/15** (Slim, Mezzio):
```php
use JDZ\Captcha\Session\PsrSession;

$captcha = new Captcha($config, new PsrSession());
```

You can also implement `CaptchaSessionInterface` for any other framework.

## Configuration

Pass options to `CaptchaConfig` or load from a YAML file:

```php
$config = new CaptchaConfig();
$config->loadFromFile('/path/to/config.yaml');
```

### Available Options

| Option | Default | Description |
|--------|---------|-------------|
| `lang` | `'en'` | Language code |
| `token` | `true` | Enable CSRF token validation |
| `theme` | `'streamline'` | Icon package |
| `variant` | `'light'` | Color variant (`light`, `dark`) |
| `container.width` | `320` | Container width in pixels |
| `image.amount.min` | `5` | Minimum icons in grid |
| `image.amount.max` | `8` | Maximum icons in grid |
| `image.rotate` | `true` | Randomly rotate icons |
| `image.flip.horizontally` | `true` | Random horizontal flip |
| `image.flip.vertically` | `true` | Random vertical flip |
| `image.border` | `true` | Draw divider lines |
| `attempts.amount` | `3` | Max attempts before timeout |
| `attempts.timeout` | `60` | Timeout duration in seconds |
| `security.clickDelay` | `500` | Min delay between clicks (ms) |
| `security.hoverDetection` | `true` | Detect hover behavior |
| `security.invalidateTime` | `12000` | Captcha expiration time (ms) |

## Security Features

- **CSRF token protection** - Configurable token-based protection
- **Honeypot field** - Hidden field to detect bots
- **Attempt limiting** - Configurable max attempts with timeout
- **Click delay** - Minimum delay between clicks to deter automation
- **Hover detection** - Detects human mouse behavior
- **Session expiration** - Captcha data expires after 30 minutes
- **Single-use images** - Each image can only be requested once

## Frontend Integration

Retrieve the JavaScript configuration for the frontend:

```php
$jsConfig = $captcha->getJsConfig(); // returns only non-default values
$token = $captcha->getToken()->make();
```

## Testing

```bash
composer test
```

## License

Proprietary - Copyright (c) 2018-present Joffrey Demetz. See [LICENSE](LICENSE) for details.
