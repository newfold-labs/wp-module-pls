---
name: wp-module-pls
title: Dependencies
description: Composer and npm dependencies.
updated: 2026-08-13
---

# Dependencies

**Runtime:** newfold-labs/wp-module-data. **Dev:** newfold-labs/wp-php-standards, wp-cli/i18n-command, wp-cli/wp-cli, johnpbloch/wordpress, lucatume/wp-browser, phpunit/phpcov.

**npm (dev):** @wordpress/env ^11.13.0, used for the local WordPress environment and the MySQL service the wpunit suite connects to.

`adm-zip` is held at `^0.6.0` through the `overrides` block in `package.json`. wp-env still requires `^0.5.9`, and the fix for the crafted-ZIP memory allocation advisory only landed in 0.6.0. Drop the override once wp-env relaxes its constraint.
