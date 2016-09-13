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
