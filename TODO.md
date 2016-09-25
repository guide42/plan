TODO
====

- [ ] `assert\required`/`assert\extra` to use in `assert\dict`.

      ```php
      $type = assert\dict(array(
          'name' => assert\all(assert\required(), 'John'),
          'name' => assert\required('John'),
      ));
      ```

- [ ] `asset\one` like `assert\any` but strict only one; or add a `$count`
  parameter to `assert\any` to count the exact times that a validator was
  success.

### 2.0

- Move to PHP 7.
- Make `Invalid` exception accept a message template and parameters to be
  constructed on `Invalid::getMessage`.
