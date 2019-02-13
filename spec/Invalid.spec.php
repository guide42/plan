<?php declare(strict_types=1);

use plan\Invalid;

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