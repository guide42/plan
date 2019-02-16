### Last Version

### 0.4.0 (2019-02-16)

  - Rename `assert\dictkeys` to `assert\keys`.
  - Move `filter\template` inside `Invalid`.
  - Remove `filter\template`.
  - Move `Schema`, `Invalid` and `MultipleInvalid` to its own files.
  - Require all structure keys in `assert\dict` by default.

### 0.3.0 (2019-01-21)

  * Require PHP 7.2.
  * Use `kahlan` for testing.
  * Add `validate` and `check` functions.
  * Add `assert\datetime` and `assert\iterable`.
  * Add `filter\template` and `util\repr`.
  * Re-arrange into `library/` directory.
  * Re-name exception `InvalidList` into `MultipleInvalid`.
  * Extract `Schema::compile` into `compile` function.
  * Fix `filter\vars` now returns in order.

### 0.2.1 (2017-04-03)

  * Better error messages in `assert\file`.
  * Add `filter\datetime`.

### 0.2.0 (2016-12-03)

  * Remove `assert\regexp`.
  * Now `Invalid` accepts template and parameters.
  * PHP >=5.6 required.

### 0.1.3 (2016-10-08)

  * Add `InvalidList::getMessages`.
  * Add `assert\file` as an alias of `assert\dict`.

### 0.1.2 (2016-10-26)

  * Mark `assert\regexp` as deprecated.
  * Add `filter\intl\alpha` y `filter\intl\alnum`.
  * Add `assert\dictkeys`.

### 0.1.1-bis (2016-09-19)

  * Add `assert\match` as wrapper around `preg_match`.
  * Add `filter\intl` namespace.
  * Add `filter\intl\chars`.

### 0.1.1 (2016-09-13)

  * Fix bug with calling `assert\object` with a non object and ask to be cloned.
  * Fix `$path` in schemas called by `assert\all`.
  * Parameter extra for `assert\dict` accepts a schema dict.

### 0.1.0-bis (2014-10-19)

  * Move type validators outside closures.

### 0.1.0 (2014-09-29)

  * New `assert\iif` for simple conditionals.
  * Finish a basic `assert\object`.

### 0.1.0-RC2 (2014-09-01)

  * Add base to `filter\intval`.
  * Make `filter\boolval` php <5.5 compatible.
  * Make `Invalid` compatible with `Exception`.
  * New `filter\sanitize` and `filter\vars`.
  * Parameter extra for `assert\dict` accept an array.

### 0.1.0-RC1 (2014-08-07)

  * Initial release
