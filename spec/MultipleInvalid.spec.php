<?php declare(strict_types=1);

use plan\{Invalid, MultipleInvalid};

describe('MultipleInvalid', function() {
    it('is an iterator', function() {
        $errors = [new Invalid('Not valid')];
        $iterator = new MultipleInvalid($errors);

        expect(iterator_to_array($iterator))->toBe($errors);
    });

    describe('__construct', function() {
        it('accepts a list of errors', function() {
            $errors = [new Invalid('Not valid')];
            $error = new MultipleInvalid($errors);

            expect($error->getErrors())->toBe($errors);
        });
        it('assigns a list of messages', function() {
            $error = new MultipleInvalid([
                new Invalid('Not valid'),
            ]);

            expect($error->getMessages())->toBe(['Not valid']);
        });
        it('assigns message for sequence', function() {
            $error = new MultipleInvalid([
                new Invalid('Not valid'),
            ]);

            expect($error->getMessage())->toBe('[ Not valid ]');
        });
        it('assigns message for dictionary', function() {
            $error = new MultipleInvalid(array(
                'foobar' => new Invalid('Not valid'),
            ));

            expect($error->getMessage())->toBe('{ Not valid }');
        });
    });

    describe('getDepth', function() {
        it('returns invalid path depth if children has none', function() {
            $error = new MultipleInvalid(
                [new Invalid('Not valid')],
                [2, 'h']
            );

            expect($error->getDepth())->toBe(2);
        });
        it('returns highest invalid path depth between children', function() {
            $error = new MultipleInvalid(
                [
                    new Invalid('Not valid', null, ['a', 0]),
                    new Invalid('Not valid', null, ['a', 0, 'c']),
                    new Invalid('Not valid', null, ['a', 'b']),
                ],
                [2]
            );

            expect($error->getDepth())->toBe(3);
        });
    });

    describe('getFlatErrors', function() {
        it('returns a list of errors', function() {
            $error = new MultipleInvalid($errors = [
                new Invalid('Not valid'),
            ]);

            expect($error->getFlatErrors())->toBe($errors);
        });
        it('returns a list of errors with errors inside MultipleInvalid', function() {
            $error = new MultipleInvalid([
                $invalid0 = new Invalid('Not valid 0'),
                new MultipleInvalid([
                    $invalid1 = new Invalid('Not valid 1'),
                    new MultipleInvalid([
                        $invalid2 = new Invalid('Not valid 2'),
                    ]),
                ]),
            ]);

            expect($error->getFlatErrors())->toBe([$invalid0, $invalid1, $invalid2]);
        });
        it('returns a list of errors with errors inside previous', function() {
            $error = new MultipleInvalid([
                $invalid0 = new Invalid('Not valid 0', null, null, 0,
                    $invalid1 = new Invalid('Not valid 1', null, null, 0,
                        $invalid2 = new Invalid('Not valid 2')
                    )
                ),
            ]);

            expect($error->getFlatErrors())->toBe([$invalid0, $invalid1, $invalid2]);
        });
    });
});
