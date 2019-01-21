<?php declare(strict_types=1);

describe('util', function() {
    describe('repr', function() {
        it('returns string quoted', function() {
            expect(plan\util\repr('foo'))->toBe('"foo"');
        });
        it('returns string up to 47 chars', function() {
            expect(
                plan\util\repr('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')
            )
            ->toBe('"Lorem ipsum dolor sit amet, consectetur adipisc...');
        });
        it('returns array represented with square brackets', function() {
            expect(plan\util\repr([1, 2, 3]))->toBe('[1, 2, 3]');
        });
        it('returns array up to 3 elements', function() {
            expect(plan\util\repr([1, 2, 3, 4, 5, 6]))->toBe('[1, 2, 3, ...]');
        });
        it('returns object class name tag', function() {
            expect(plan\util\repr(new \stdClass))->toBe('<stdClass>');
        });
        it('returns resource type tag', function() {
            expect(plan\util\repr(STDIN))->toBe('<resource:stream>');
        });
        it('returns boolean as lower case export', function() {
            expect(plan\util\repr(true))->toBe('true');
            expect(plan\util\repr(false))->toBe('false');
        });
        it('returns integer as export', function() {
            expect(plan\util\repr(0))->toBe('0');
            expect(plan\util\repr(1))->toBe('1');
        });
        it('returns float as export', function() {
            expect(plan\util\repr(0.0))->toBe('0.0');
            expect(plan\util\repr(1.0))->toBe('1.0');
        });
    });
});
