Plan
====

Plan is a data validation library for PHP. It's planed to be used for
validating data from external sources.

It has three core design goals:

1. Speed;
2. Simplicity and Lightweight;
3. Full validation features support;

It consist in just one file separated in three _namespaces_. To start, just
have to download it and require it in your code.

Concepts
--------

Defining schemas is the first step to validate an input data. An schema is a
tree of _validators_ that can be manually defined or created based on literals
and arrays.

With the schema defined, a plan must be make to validate data. This is made
through the `\plan\Schema` class. This object, when called like a function,
will validate the data and return the modified (or not) data. If any error
occurs and exception will be thrown.

    <?php
    
    use plan\Schema as plan; // Alias it to make it nicer.
    use plan\InvalidList;    // A list of `Invalid` exceptions will be
                             // always forced by `Schema`.
    
    $plan = new plan($schema);
    
    try {
        $data = $plan($input);
    } catch (InvalidList $list) {
        foreach ($list as $error) {
            var_dump($error->getMessage());
        }
    }

Schema information will be always be trusted, therefore will not be validate.
Contrary input data will be never be trusted.

### Literals

Scalars are treated as literals that are matched using the identity operator:

    $plan = new plan('Hello World');
    $plan('Hello World'); // returns 'Hello World'
    
    $plan = new plan(42);
    
    try {
        $plan(10);
    } catch (InvalidList $e) {
        // $e->getMessage() will be
        // Multiple invalid: ["\"10\" is not \"42\""]
    }

### Arrays

Plan will distinguish between indexed and associative arrays. If an array has
all indexes numeric and sequential will be considerer a sequence. If not will
be considerer a dictionary.

#### Sequences

A sequence will be treated as a list of possible valid values. Will require
that the input data is sequence that contains one or more elements of the
schema. Elements can be repeated.

    $plan = new plan([1, 'one']);
    $plan([1]);
    $plan([1, 'one', 1, 1, 'one', 1]);

An empty array will be a sequence that accept any value.

    $plan = new plan([]);
    $plan(['anything', 123, true);

#### Dictionaries

A dictionary will be used to validate structures. Each key in data will be
checked with the _validator_ of the same key in the schema. By default, keys
are not required; but any additional key will throw an exception.

    $plan = new plan(array('name' => 'John', 'age'  => 42));
    $plan(array('age' => 42));
    
    try {
        $plan(array('age' => 42, 'sex' => 'male');
    } catch (InvalidList $e) {
        // Multiple invalid: ["Extra key sex not allowed"]
    }

Validators
----------

All core _validators_ live in `\plan\assert` _namespace_.

### `type`

Will validate the type of data. The data type will be not casted.

    $plan = new plan(assert\type('int'));
    $plan(123);
    
    try {
        $plan('123');
    } catch (InvalidList $e) {
        // Multiple invalid: ["123 not int"]
    }

Aliases of this _validator_ are: `bool`, `int`, `float`, `str`.

### `scalar`

Wrapper around `is_scalar` function.

### `instance`

Wrapper around `instanceof` type operator.

### `literal`

See [Literals](#literals).

### `seq`

See [Sequences](#sequences).

This is normally accepted as "a list of something (or something else)".

*   A list of email? `new plan([assert\email()])`.
*   A list of people, but some of them are in text and some as a dictionary?

        $plan = new plan([assert\str(), array(
            'name'  => assert\str(),
            'email' => assert\email(),
        )]);
        $plan([
            array('name' => 'Kevin', 'email' => 'k@viewaskew.com'),
            array('name' => 'Jane', 'email' => 'jane@example.org'),
            'John Doe <john@example.org>',
        ]);

### `dict`

See [Dictionaries](#dictionaries).

Because, by default keys are not required and extra keys throw exceptions,
the _validator_ `dict` accept two more parameters to change this behavior.

    $required = true; // Will require ALL keys
    $extra    = true; // Accept extra keys
    
    $dict = array('name' => 'John', 'age' => 42);
    $plan = new plan(assert\dict($dict, $required, $extra);
    $plan(array(
        'name' => 'John',
        'age'  => 42,
        'sex'  => 'male', // This could be whatever
                          // as it would not be validated
    ));

### `any`

Accept any of the given list of _validators_, as a valid value. This is useful
when you only need one choice a of set of values. If you need any quantity
of choices use a [sequence](#sequence) instead.

    $plan = new plan(array(
        'Connection' => assert\any('ethernet', 'wireless'),
    ));
    $plan(array('Connection' => 'ethernet'));
    $plan(array('Connection' => 'wireless'));

### `all`

Require all _validators_ to be valid.

    $plan = new plan(assert\all(assert\str(), assert\length(3, 17)));
    $plan('Hello World');

### `not`

Negative the given _validator_.

    $plan = new plan(assert\not(assert\str()));
    $plan(true);
    $plan(123);
    
    try {
        $plan('fail');
    } catch (InvalidList $e) {
        // Multiple invalid: ["Validator passed"]
    }

### `length`

The given data length is between some minimum and maximum value. This works
with strings using `strlen` or `count` for everything else.

    $plan = new plan(assert\length(2, 4));
    $plan('abc');
    $plan(array('a', 'b', 'c'));

### `validate`

A wrapper for validate filters using `filter_var`. It accepts the name of the
filter as listed [here](http://php.net/manual/en/filter.filters.validate.php).

    $plan = new plan(assert\validate('validate_email'));
    $plan('john@example.org');

Aliases are: `url`, `email`, `ip`, `regexp`.

And the "like-type": `boolval`, `intval`, `floatval`. Note that this will check
that a string resemble to a boolean/int/float; for checking if the input data
**IS** a boolean/int/float use the `type` _validator_. None of this will
modify the input data.

Filters
-------

The input data can also be filtered, and the validation will return the
modified data. By convention is called _validator_ when it will not modified
the input data; and _filter_ when modification to the data are performed.

Core _filters_ will be found in the `\plan\filter` _namespace_.

### `type`

Will cast the data into the given type.

    $plan = new plan(filter\type('int'));
    $plan('123 users'); // Returns 123

Note that `boolval`, `intval`, `floatval` are not aliases of this filter but
wrappers of the homonymous functions.

Writing Validators
------------------

A simple _callable_ can be a _validator_.

Any validation error is thrown with the `Invalid` exception. If several errors
must be reported, `InvalidList` is an exception that could contain several
exceptions. All other exceptions are considerer as errors in the _validator_.

    $passwordStrength = function($data, $path=null)
    {
        $type = assert\str(); // Use another validator to check that $data is
        $data = $type($data); // an string, if not will throw an exception.

        // Because we are going to throw more than one error, we will
        // accumulate in this variable.
        $errors = array();

        if (strlen($data) < 8) {
            $errors[] = new Invalid('Must be at least 8 characters');
        }

        if (!preg_match('/[A-Z]/', $data)) {
            $errors[] = new Invalid('Must have at least one uppercase letter');
        }

        if (!preg_match('/[a-z]/', $data)) {
            $errors[] = new Invalid('Must have at least one lowercase letter');
        }

        if (!preg_match('/\d/', $data)) {
            $errors[] = new Invalid('Must have at least one digit');
        }

        if (count($errors) > 0) {
            throw new InvalidList($errors);
        }

        // If everything went OK, we return the data so it can continue to be
        // checked by the chain.
        return $data;
    };

    $validator = new plan($passwordStrength);
    $validated = $validator('heLloW0rld');

Acknowledgments
---------------

This library is heavily inspired in [Voluptuous][] by Alec Thomas.

[Voluptuous]: https://github.com/alecthomas/voluptuous

Badges
------

[![Build Status](https://travis-ci.org/guide42/plan.svg)](https://travis-ci.org/guide42/plan)
