# Smile Lab PHPMD Ruleset

## Description

This ruleset is meant to be used on Magento projects and modules.

Custom rules are copied from https://github.com/magento/magento2/tree/2.4.5/dev/tests/static/framework/Magento/CodeMessDetector.

## Installation

To use this ruleset, require it in composer:

```shell
composer require --dev smile/magento2-smilelab-phpmd
```

## Usage

You can run phpmd with this command:

```shell
php vendor/bin/phpmd [src folder] text vendor/smile/magento2-smilelab-phpmd/ruleset.xml
```
