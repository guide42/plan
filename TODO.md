TODO
====

* Make `assert\required`/`assert\extra` to use in `assert\dict`
      $type = assert\dict(array(
          'name' => assert\all(assert\required(), 'John'),
          'name' => assert\required('John'),
      ));
* Finish `assert\object`
      $type = assert\all(
          assert\type('object'),
          assert\instance($class),
          filter\vars(false, true),
          assert\dict($structure, false, true),
          filter\bless($class) // http://php.net/manual/en/function.get-object-vars.php#47075
      );
