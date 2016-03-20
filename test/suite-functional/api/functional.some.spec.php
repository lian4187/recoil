<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;

rit('executes the coroutines', function () {
    ob_start();
    yield Recoil::some(
        3,
        function () {
            echo 'a';
            yield;
        },
        function () {
            echo 'b';
            yield;
        },
        function () {
            echo 'c';
            yield;
        }
    );
    expect(ob_get_clean())->to->equal('abc');
});

rit('terminates the substrands when the calling strand is terminated', function () {
    $strand = yield Recoil::execute(function () {
        yield (function () {
            yield Recoil::some(
                2,
                function () { yield; assert(false, 'not terminated'); },
                function () { yield; assert(false, 'not terminated'); }
            );
        })();
    });

    yield;

    $strand->terminate();
});

context('when the required number of substrands succeed', function () {
    rit('resumes the calling strand with an array of return values', function () {
        expect(yield Recoil::some(
            2,
            function () {
                yield;

                return 'a';
            },
            function () {
                return 'b';
                yield;
            },
            function () {
                return 'c';
                yield;
            }
        ))->to->equal([
            1 => 'b',
            2 => 'c',
        ]);
    });

    rit('terminates the remaining strands', function () {
        yield Recoil::some(
            1,
            function () {
                yield;
                assert(false, 'not terminated');
            },
            function () {
                return;
                yield;
            }
        );
    });
});

context('when too many substrands fail', function () {
    rit('resumes the calling strand with a composite exception', function () {
        try {
            yield Recoil::some(
                2,
                function () { yield Recoil::terminate(); },
                function () { throw new Exception('<exception>'); yield; },
                function () { yield; }
            );
            assert(false, 'expected exception was not thrown');
        } catch (CompositeException $e) {
            expect($e->exceptions())->to->have->length(2);
            expect($e->exceptions()[0])->to->be->an->instanceof(TerminatedException::class);
            expect($e->exceptions()[1])->to->be->an->instanceof(Exception::class);
        }
    });

    rit('sorts the previous exceptions based on the order that the substrands exit', function () {
        try {
            yield Recoil::some(
                2,
                function () { yield; yield; throw new Exception('<exception-a>'); },
                function () { yield; throw new Exception('<exception-b>'); },
                function () { yield; }
            );
            assert(false, 'expected exception was not thrown');
        } catch (CompositeException $e) {
            expect(array_keys($e->exceptions()))->to->equal([1, 0]);
        }
    });
});
