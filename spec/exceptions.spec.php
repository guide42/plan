<?php declare(strict_types=1);

use plan\{Invalid, MultipleInvalid};

describe('Invalid', function() {
    describe('__construct', function() {
        it('accepts message', function() {
            $message = 'This is the message';
            $invalid = new Invalid($message);

            expect($invalid->getMessage())->toBe($message);
        });
        it('accepts template and context', function() {
            $template = 'This is {subject}';
            $context = array(
                'subject' => 'the message',
            );

            $invalid = new Invalid($template, $context);

            expect($invalid->getTemplate())->toBe($template);
            expect($invalid->getContext())->toBe($context);
            expect($invalid->getMessage())->toBe('This is the message');
        });
        it('accepts path', function() {
            $path = [0, 'hello'];
            $invalid = new Invalid('Hello', null, $path);

            expect($invalid->getPath())->toBe($path);
        });
        it('accepts code', function() {
            $code = 220;
            $invalid = new Invalid('Not valid', null, null, $code);

            expect($invalid->getCode())->toBe(220);
        });
        it('accepts previous', function() {
            $previous = new Invalid('Previous');
            $invalid = new Invalid('Not valid', null, null, 0, $previous);

            expect($invalid->getPrevious())->toBe($previous);
        });
        it('appends previous message to owns message', function() {
            $previous = new Invalid('Previous');
            $invalid = new Invalid('Not valid', null, null, 0, $previous);

            expect($invalid->getMessage())->toBe('Not valid: Previous');
        });
    });

    describe('getDepth', function() {
        it('returns zero if there is no path', function() {
            $invalid = new Invalid('Not valid');
            $depth = $invalid->getDepth();

            expect($depth)->toBe(0);
        });
        it('returns the count of path', function() {
            $invalid = new Invalid('Not valid', null, [2, 'h']);
            $depth = $invalid->getDepth();

            expect($depth)->toBe(2);
        });
    });
});

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
