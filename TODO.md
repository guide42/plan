TODO
====

- [ ] Make `assert\required`/`assert\extra` to use in `assert\dict`

      ```php
      $type = assert\dict(array(
          'name' => assert\all(assert\required(), 'John'),
          'name' => assert\required('John'),
      ));
      ```
