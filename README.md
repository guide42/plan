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

Acknowledgments
---------------

This library is heavily inspired in [Voluptuous][] by Alec Thomas.

[Voluptuous]: https://github.com/alecthomas/voluptuous
