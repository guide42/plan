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
and arrays. A simple _callable_ can be a _validator_.

Any validation error is thrown with the `Invalid` exception. If several errors
must be reported, `InvalidList` is an exception that could contain several
exceptions. All other exceptions are considerer as errors in the _validator_.

The input data can also be filtered, and the validation will return the
modified (or not) data. If any error happened must be cached.

### Literals

Scalars are treated as literals that are matched using the identity operator:

    use plan\Schema as plan;
    use plan\InvalidList;
    
    $schema = new plan('Hello World');
    $schema('Hello World'); // returns 'Hello World'
    
    $schema = new plan(42);
    
    try {
        $schema(10);
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

    $schema = new plan([1, 'one']);
    $schema([1]);
    $schema([1, 'one', 1, 1, 'one', 1]);

An empty array will be a sequence that accept any value.

    $schema = new plan([]);
    $schema(['anything', 123, true);

#### Dictionaries

A dictionary will be used to validate structures. Each key in data will be
checked with the _validator_ of the same key in the schema. By default, keys
are not required; but any additional key will throw an exception.

    $schema = new plan(array('name' => 'John', 'age'  => 42));
    $schema(array('age' => 42));
    
    try {
        $schema(array('age' => 42, 'sex' => 'male');
    } catch (InvalidList $e) {
        // Multiple invalid: ["Extra key sex not allowed"]
    }

Validators
----------

All core _validators_ live in `\plan\assert` _namespace_.

### `type`

Will validate the type of data. The data type will be not casted.

    $schema = new plan(assert\type('int'));
    $schema(123);
    
    try {
        $schema('123');
    } catch (InvalidList $e) {
        // Multiple invalid: ["123 not boolean"]
    }

Aliases of this _validator_ are: `boolean`, `int`, `float`, `str`.

### `scalar`

Wrapper around `is_scalar` function.

### `literal`

See [Literals](#literals).

### `seq`

See [Sequences](#sequences).

This is normally accepted as "a list of something (or something else)".

*   A list of email? `new plan([assert\email()])`.
*   A list of people, but some of them are in text and some as a dictionary?

        $schema = new plan([assert\str(), array(
            'name'  => assert\str(),
            'email' => assert\email(),
        )]);
        $schema([
            array('name' => 'Kevin', 'email' => 'k@viewaskew.com'),
            array('name' => 'Jane', 'email' => 'jane@example.org'),
            'John Doe <john@example.org>',
        ]);

### `dict`

See [Dictionaries](#dictionaries).

Because, by default keys are not required and extra keys throw exceptions,
the _validator_ `dict` accept two more parameters to change this behavior.

    $required = true;  // Will require ALL keys
    $extra    = false; // Accept extra keys
    
    $person = array('name' => 'John', 'age' => 42);
    $schema = new plan(assert\dict($person, $required, $extra);
    $schema(array(
        'name' => 'John',
        'age'  => 42,
        'sex'  => 'male', // This could be whatever
                          // as it would not be validated
    ));

### `any`

Accept any of the given list of _validators_, as a valid value. This is useful
when you only need one choice a of set of values. If you need any quantity
of choices use a [sequence](#sequence) instead.

    $schema = new plan(array(
        'Connection' => assert\any('ethernet', 'wireless'),
    ));
    $schema(array('Connection' => 'ethernet'));
    $schema(array('Connection' => 'wireless'));

### `all`

Require all _validators_ to be valid.

    $schema = new plan(assert\all(assert\str(), assert\length(3, 17)));
    $schema('Hello World');

### `not`

Negative the given _validator_.

    $schema = new plan(assert\not(assert\str()));
    $schema(true);
    $schema(123);
    
    try {
        $schema('fail');
    } catch (InvalidList $e) {
        // Multiple invalid: ["Validator passed"]
    }

### `length`

The given data length is between some minimum and maximum value. This works
with strings using `strlen` or `count` for everything else.

    $schema = new plan(assert\length(2, 4));
    $schema('abc');
    $schema(array('a', 'b', 'c'));

### `validate`

A wrapper for validate filters using `filter_var`. It accepts the name of the
filter as listed [here](http://php.net/manual/en/filter.filters.validate.php).

    $schema = new plan(assert\validate('validate_email'));
    $schema('john@example.org');

Aliases are: `url`, `email`, `ip`, `regexp`.

Acknowledgments
---------------

This library is heavily inspired in [Voluptuous][] by Alec Thomas.

[Voluptuous]: https://github.com/alecthomas/voluptuous
