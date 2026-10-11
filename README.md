# JDZ Captcha

A PHP icon-based CAPTCHA library. Instead of distorted text, users identify and select the image displayed the least number of times in a randomized grid of icons.

See [jdzcaptcha.joffreydemetz.com](https://jdzcaptcha.joffreydemetz.com) for more information on the PHP/JS implementation.

## Requirements

- PHP >= 8.2
- GD extension (`ext-gd`)
- [`jdz/data`](https://jdz.joffreydemetz.com/data) ^2.0
- `symfony/yaml` ^7.4
- Optional: `symfony/http-foundation` for the `SymfonySession` adapter

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
```

### HTTP Endpoints

The widget talks to three endpoints. `JDZ\Captcha\Http\CaptchaHttpHandler`
implements them framework-free; each method returns a `JDZ\Captcha\Http\HttpResult`
(`status`, `body`, lowercase `headers`) that your controller maps onto its own
response type (PSR-7, Symfony, or plain `header()` / `echo`):

| Route | Handler call |
|-------|--------------|
| `POST /captcha/load` | `load()` — the widget configuration (JSON) |
| `GET /captcha/request?payload=…` | `image($payload)` — the single-use challenge image (PNG) |
| `POST /captcha/request` | `action($rawBody, $headerToken)` — image hashes, answer selection, interaction timeout |

```php
use JDZ\Captcha\Http\CaptchaHttpHandler;

$handler = new CaptchaHttpHandler($captcha);
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$result = match (true) {
    '/captcha/load' === $path => $handler->load(),
    'GET' === $_SERVER['REQUEST_METHOD'] => $handler->image($_GET['payload'] ?? ''),
    default => $handler->action((string) file_get_contents('php://input'), $_SERVER['HTTP_X_JDZCAPTCHA_TOKEN'] ?? ''),
};

http_response_code($result->status);
foreach ($result->headers as $name => $value) {
    header($name . ': ' . $value);
}
echo $result->body;
```

An invalid or replayed image request answers 403; any other bad input (or an
unexpected failure) answers 400. Transport concerns stay with you: no-cache
headers on the image, CORS, and so on. The paths are configurable (`path`,
`loaderPath`).

### Lower-level API

The handler is built on these `Captcha` methods, which you can also call
directly:

```php
// Generate captcha data for a theme (returns base64-encoded JSON)
$data = $captcha->getCaptchaData('light', 1);

// Generate the image (\GdImage, or false)
$image = $captcha->getImage(1);
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
| `series` | `'streamline'` | Icon package |
| `theme` | `'light'` | Color variant (`light`, `dark`) |
| `path` | `'/captcha/request/'` | Endpoint for the image and the ajax actions |
| `loaderPath` | `'/captcha/load/'` | Endpoint for the widget configuration |
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
- **Attempt limiting** - Configurable max attempts with timeout; a wrong answer (a click or a form post) spends the puzzle, so each puzzle gets one try and the widget loads a new one
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

## Changelog

- **2.2.2** - **Security**: a wrong answer spends the puzzle. A wrong click (`setSelectedAnswer()`) or a wrong form post (`validate()`) drops the puzzle's icons and answer, so the same id answered again is refused (`validate()`: code 4, "Invalid Captcha ID"); the attempt is counted either way, and the widget's next puzzle carries the count and the lock. A bot gets one try per puzzle and `attempts.amount` puzzles per lock window. `SymfonySession::has()` reads a stored `null` as absent, like the Native and PSR adapters. No widget change: jizy-captcha already reloads a puzzle after a wrong answer.
- **2.2.1** - Answers are refused while the captcha is locked out, and every wrong answer past the limit locks again; a malformed or out-of-range click is a wrong answer instead of a PHP error; session cleaning quotes the namespace.
- **2.2.0** - `CaptchaHttpHandler::image()` maps unexpected failures to 400.
- **2.1.0** - HTTP layer: `Http\CaptchaHttpHandler` + `Http\HttpResult`.
- **2.0.4 / 2.0.5** - MIT license.

## Acknowledgements

This project was initially inspired by [IconCaptcha v3.1.1](https://github.com/fabianwennink/IconCaptcha-PHP).

## License

MIT - Copyright (c) 2018-present Joffrey Demetz. See [LICENSE](LICENSE) for details.
