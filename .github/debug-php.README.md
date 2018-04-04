<!-- Markdown link definitions -->
[init-base]: https://github.com/josephtingiris/email-php
[init-conduct]: email-php.CODE_OF_CONDUCT.md
[init-contributing]: email-php.CONTRIBUTING.md
[init-installation]: #Installation
[init-issue]: https://github.com/josephtingiris/email-php/issues/new
[init-license]: email-php.LICENSE.md
[init-support]: #Support
[init-usage]: #Usage
[init-wiki]: https://github.com/josephtingiris/email-php/wiki

# Description

This is a structure for my PHP Email class composer project.

## Table of Contents

* [Installation][init-installation]
* [Usage][init-usage]
* [Support][init-support]
* [License][init-license]
* [Code of Conduct][init-conduct]
* [Contributing][init-contributing]

## Installation

Download to the project directory, add, and commit.  i.e.:

```sh
composer require "josephtingiris/email-php"
```

## Usage

1. Basic, send a plain text email, to a single recipient, via php.

```php
<?php

require_once(dirname(__FILE__) . "/vendor/autoload.php");

$Email = new \josephtingiris\Email();

$Email->send("joseph.tingiris@gmail.com","test subject,"test message; plain text");
?>
```

## Support

Please see the [Wiki][init-wiki] or [open an issue][init-issue] for support.
