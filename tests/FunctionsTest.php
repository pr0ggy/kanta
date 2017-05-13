<?php

namespace Kanta\Test;

use DateTime;
use stdClass;
use Exception;
use ArrayObject;
use DateTimeZone;
use LogicException;
use RuntimeException;
use Kanta\Test\TestUtils;
use Kanta\Validation as v;
use Kanta\Validation\AssertionFailureException;
use Kanta\Validation\ValidationFailureException;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    /**
     * @test
     * @dataProvider malformedAssertionDataProvider
     */
    public function assert_throws_exception_if_problem_with_given_assertion_data($assertionData, $expectedProblemMessage)
    {
        try {
            v\assert($assertionData);
            $this->fail("assert did not throw exception even though there was a problem with the given assertion data:\n{$expectedProblemMessage}");
        } catch (RuntimeException $exception) {
            $this->assertEquals(
                $expectedProblemMessage,
                $exception->getMessage(),
                'Exception thrown, but message was not as expected'
            );
        }
    }

    public function malformedAssertionDataProvider() {
        yield [
            [],
            v\NO_SUBJECT_SPECIFIED_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'thatCalling' => 'is_int'
            ],
            v\MULTIPLE_SUBJECTS_SPECIFIED_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'thatCalling' => []
            ],
            v\MULTIPLE_SUBJECTS_SPECIFIED_ERROR
        ];

        yield [
            [
                'that' => new Exception()
            ],
            v\MISSING_FAILURE_REASON_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'orFailBecause' => 'something went wrong'
            ],
            v\MISSING_VALUE_SUBJECT_VALIDATOR_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'satisfies' => false,
                'orFailBecause' => 'something went wrong'
            ],
            v\VALUE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'satisfies' => [$this, 'someMissingMethod'],
                'orFailBecause' => 'something went wrong'
            ],
            v\VALUE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR
        ];

        yield [
            [
                'that' => new Exception(),
                'satisfies' => [
                    v\is(false),
                    null
                ],
                'orFailBecause' => 'something went wrong'
            ],
            v\VALUE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR
        ];

        $someFunc = function() { /* no-op */ };
        yield [
            [
                'thatCalling' => $someFunc,
                'orFailBecause' => 'something went wrong'
            ],
            v\MISSING_CALLABLE_SUBJECT_VALIDATOR_ERROR
        ];

        yield [
            [
                'thatCalling' => false,
                'throwsExceptionSatisfying' => [],
                'orFailBecause' => 'something went wrong'
            ],
            v\CALLABLE_SUBJECT_IS_NOT_CALLABLE_ERROR
        ];

        yield [
            [
                'thatCalling' => $someFunc,
                'throwsExceptionSatisfying' => false,
                'orFailBecause' => 'something went wrong'
            ],
            v\CALLABLE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR
        ];

        yield [
            [
                'thatCalling' => $someFunc,
                'throwsExceptionSatisfying' => [
                    false,
                    v\isInstanceOf('RuntimeException')
                ],
                'orFailBecause' => 'something went wrong'
            ],
            v\CALLABLE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR
        ];
    }

    /**
     * @test
     * @expectedException Exception
     * @expectedExceptionMessage Validation failure message
     */
    public function fail_explicitly_fails_by_throwing_exception()
    {
        v\fail('Validation failure message');
    }

    /**
     * @test
     * @dataProvider unequalValuesAndFailureMessageProvider
     */
    public function is_generates_callable_that_throws_if_values_in_question_do_not_exhibit_strict_equality($expectedValue, $actualValue, $expectedFailureMessage)
    {
        $equalityTest = v\is($expectedValue);
        try {
            $equalityTest($actualValue);
            // if the above call doesn't throw, test has failed
            $this->fail("
                No exception thrown when asserting that inequal values are equal
                \n\nOBJECT A:\n"
                .var_export($expectedValue, true)
                ."\n\nOBJECT B:\n"
                .var_export($actualValue, true)
            );
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                $expectedFailureMessage,
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    public function unequalValuesAndFailureMessageProvider()
    {
        yield [true, '0', 'expected type boolean differs from received type: string'];
        yield [['test'], new stdClass(), 'expected type array differs from received type: object'];
        yield [5, ['test'], 'expected type integer differs from received type: array'];
        yield [5, '5', 'expected type integer differs from received type: string'];

        yield [true, false, 'false does not equal expected value: true'];
        yield [3, 5, '5 does not equal expected value: 3'];
        yield ['foo', 'bar', "
--- Expected
+++ Actual
@@ @@
-'foo'
+'bar'
"];
    }

    /**
     * @test
     * @dataProvider equalValuesProvider
     */
    public function is_generates_callable_that_passes_if_values_in_question_do_exhibit_strict_equality($expectedValue, $actualValue)
    {
        $equalityTest = v\is($expectedValue);
        try {
            $equalityTest($actualValue);
        } catch (v\ValidationFailureException $exception) {
            $this->fail("
                Exception thrown when asserting that equal values are equal
                \n\nOBJECT A:\n"
                .var_export($expectedValue, true)
                ."\n\nOBJECT B:\n"
                .var_export($actualValue, true)
            );
        }
    }

    public function equalValuesProvider()
    {
        yield [0, 0];
        yield [false, false];
        yield [1, 1];
        yield ['1', '1'];
        yield [true, true];
        yield [['test'], ['test']];
    }

    /**
     * @test
     * @dataProvider nonObjectProvider
     */
    public function isInstaceOf_generates_callable_that_throws_if_given_non_object($nonObject, $stringRep)
    {
        $typeCheck = v\isInstanceOf('SomeClass');
        try {
            $typeCheck($nonObject);
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                "Given entity was not an object: {$stringRep}",
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    public function nonObjectProvider()
    {
        yield [null, 'null'];
        yield [5, '5'];
        yield ['1', "'1'"];
        yield [true, 'true'];
        yield [['test'], "array"];
    }

    /**
     * @test
     * @expectedException Kanta\Validation\ValidationFailureException
     * @expectedExceptionMessage Given object was an instance of RuntimeException instead of Exception as expected
     */
    public function isInstaceOf_generates_callable_that_throws_if_given_object_is_not_an_instance_of_the_specified_class()
    {
        $typeCheck = v\isInstanceOf('Exception');
        $typeCheck(new RuntimeException());
    }

    /**
     * @test
     */
    public function isInstaceOf_generates_callable_that_passes_if_given_object_is_instance_of_the_specified_class()
    {
        $typeCheck = v\isInstanceOf('RuntimeException');
        $typeCheck(new RuntimeException());
    }

    /**
     * @test
     * @dataProvider nonObjectProvider
     */
    public function isTypeOf_generates_callable_that_throws_if_given_non_object($nonObject, $stringRep)
    {
        $typeCheck = v\isTypeOf('SomeClass');
        try {
            $typeCheck($nonObject);
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                "Given entity was not an object: {$stringRep}",
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    /**
     * @test
     * @dataProvider objectTypeMismatchProvider
     */
    public function isTypeOf_generates_callable_that_throws_if_given_object_is_not_an_instance_of_the_specified_type($object, $objectType, $classOrInterfaceExpected)
    {
        $typeCheck = v\isTypeOf($classOrInterfaceExpected);
        try {
            $typeCheck($object);
            $this->fail('No exception thrown when validating that an object is of a given type when it is not');
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                "Given object was instance of {$objectType}, which is not a type of {$classOrInterfaceExpected}",
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    public function objectTypeMismatchProvider()
    {
        yield [new Exception(), 'Exception', 'Iterable'];
        yield [new LogicException(), 'LogicException',  'RuntimeException'];
        yield [new stdClass(), 'stdClass',  'Exception'];
        yield [new stdClass(), 'stdClass',  'countable'];
    }

    /**
     * @test
     * @dataProvider objectTypeMatchProvider
     */
    public function isTypeOf_generates_callable_that_passes_if_given_object_of_the_specified_type($object, $classOrInterface)
    {
        $typeCheck = v\isTypeOf($classOrInterface);
        $typeCheck($object);
    }

    public function objectTypeMatchProvider()
    {
        yield [new Exception(), 'Throwable'];
        yield [new LogicException(), 'Exception'];
        yield [new ArrayObject([]), 'Countable'];
        yield [new DateTime(), 'DateTimeInterface'];
    }

    /**
     * @test
     * @dataProvider failingKeyContainmentProvider
     */
    public function hasKeys_generates_callable_that_throws_ValidationFailureException_if_given_entity_does_not_contain_the_specified_keys($keys, $entity, $expectedFailureMessage)
    {
        $keyCheck = v\hasKeys(...$keys);
        try {
            $keyCheck($entity);
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                $expectedFailureMessage,
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }

    }

    public function failingKeyContainmentProvider()
    {
        yield [
            ['biz'],
            false,
            'Given entity was not traversable: false'
        ];

        yield [
            ['biz'],
            ['foo' => 'bar'],
            'Key not found: biz'
        ];

        yield [
            ['foo', 'bat', 'biz'],
            ['foo' => 'bar', 'biz' => 'baz'],
            'Key not found: bat'
        ];

        yield [
            ['foo', 'bat', 'biz'],
            new ArrayObject(['foo' => 'bar', 'biz' => 'baz']),
            'Key not found: bat'
        ];
    }

    /**
     * @test
     * @dataProvider passingKeyContainmentProvider
     */
    public function hasKeys_generates_callable_that_passes_if_given_entity_does_contain_the_specified_keys($keys, $entity)
    {
        $keyCheck = v\hasKeys(...$keys);
        $keyCheck($entity);
    }

    public function passingKeyContainmentProvider()
    {
        yield [
            ['foo'],
            ['foo' => 'bar']
        ];

        yield [
            ['foo', 'bat', 'biz'],
            ['foo' => 'bar', 'biz' => 'baz', 'bat' => 'bif', 'bam' => 'fizz']
        ];

        yield [
            ['foo', 'bat', 'biz'],
            new ArrayObject(['foo' => 'bar', 'biz' => 'baz', 'bat' => 'bif', 'bam' => 'fizz'])
        ];
    }

    /**
     * @test
     * @dataProvider nonTraversableProvider
     */
    public function isTraversable_generates_callable_that_throws_ValidationFailureException_if_the_given_entity_is_not_traversable($entity, $expectedFailureMessage)
    {
        $traversableCheck = v\isTraversable();
        try {
            $traversableCheck($entity);
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                $expectedFailureMessage,
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }

    }

    public function nonTraversableProvider()
    {
        yield [null, 'Given entity was not traversable: null'];
        yield [false, 'Given entity was not traversable: false'];
        yield ['foo', "Given entity was not traversable: 'foo'"];
        yield [new stdClass(), 'Given entity was not traversable: object'];
    }

    /**
     * @test
     * @dataProvider traversableProvider
     */
    public function isTraversable_generates_callable_that_passes_if_the_given_entity_is_traversable($entity)
    {
        $traversableCheck = v\isTraversable();
        $traversableCheck($entity);
    }

    public function traversableProvider()
    {
        yield [ [] ];
        yield [ ['foo'] ];
        yield [ new ArrayObject(['foo']) ];
    }

    /**
     * @test
     * @expectedException Kanta\Validation\ValidationFailureException
     * @expectedExceptionMessage Key not found: biz
     */
    public function hasKVPair_generates_callable_that_throws_ValidationFailureException_if_given_entity_does_not_contain_the_specified_key()
    {
        $keyCheck = v\hasKVPair('biz', 'baz');
        $keyCheck(['foo' => 'bar']);
    }

    /**
     * @test
     */
    public function hasKVPair_generates_callable_that_throws_ValidationFailureException_if_given_entity_has_the_specified_key_pointing_to_a_different_value()
    {
        $keyCheck = v\hasKVPair('foo', 'baz');

        try {
            $keyCheck(['foo' => 'bar']);
            $this->fail('No exception thrown when validating that an entity contians a KV pair when it does not');
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                "Key exists, but value was not as expected:

--- Expected
+++ Actual
@@ @@
-'baz'
+'bar'
",
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    /**
     * @test
     */
    public function hasKVPair_generates_callable_that_passes_if_given_entity_does_contain_the_specified_key_value_pair()
    {
        $keyCheck = v\hasKVPair('foo', 'bar');
        $keyCheck(new ArrayObject(['foo' => 'bar']));
    }

    /**
     * @test
     */
    public function hasValues_generates_callable_that_throws_ValidationFailureException_if_given_collection_does_not_contain_the_specified_values()
    {
        $containmentCheck = v\hasValues('foo', 'bar');
        try {
            $containmentCheck(['foo', 'boz', 'biz']);
            $this->fail('No exception thrown when validating that a collection contains a value when it is not');
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                "Expected value not found: 'bar'",
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    /**
     * @test
     */
    public function hasValues_generates_callable_that_passes_if_given_collection_does_contain_the_specified_values()
    {
        $containmentCheck = v\hasValues('foo', 'bar');
        $containmentCheck(['foo', 'boz', 'biz', 'bar']);
    }

    /**
     * @test
     * @expectedException Kanta\Validation\ValidationFailureException
     * @expectedExceptionMessage Count was 2 instead of 3 as expected
     */
    public function hasCountOf_generates_callable_that_throws_ValidationFailureException_if_given_collection_does_not_contain_the_specified_number_of_elements()
    {
        $countCheck = v\hasCountOf(3);
        $countCheck(['foo', 'bar']);
    }

    /**
     * @test
     */
    public function hasCountOf_generates_callable_that_passes_if_given_collection_does_contain_the_specified_number_of_elements()
    {
        $countCheck = v\hasCountOf(3);
        $countCheck(['foo', 'bar', 'baz']);
    }

    /**
     * @test
     */
    public function hasProperty_generates_callable_that_throws_ValidationFailureException_if_given_object_has_no_property_or_getter_matching_the_given_property_name()
    {
        $propertyCheck = v\hasProperty('foo');

        try {
            $propertyCheck(new Exception('something failed'));
            $this->fail('No exception thrown when validating that an object has a property when it does not');
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                'No property found with the given name: foo',
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    /**
     * @test
     * @dataProvider objectPropertyValueMismatchProvider
     */
    public function hasProperty_generates_callable_that_throws_ValidationFailureException_if_given_object_has_a_property_name_or_getter_matching_the_given_property_name_but_different_property_value(
        $object,
        $propertyName,
        $propertyValueExpected,
        $failureMessageExpected
    ) {
        $propertyCheck = v\hasProperty($propertyName, $propertyValueExpected);

        try {
            $propertyCheck($object);
            $this->fail('No exception thrown when validating that an object has a property matching a given value when the values do not match');
        } catch (v\ValidationFailureException $exception) {
            $this->assertEquals(
                $failureMessageExpected,
                $exception->getMessage(),
                'Validation exception thrown, but failure message was not as expected'
            );
        }
    }

    public function objectPropertyValueMismatchProvider()
    {
        // uses getter: getCode
        yield [new Exception('something failed'), 'code', 50, "'code' property did not have expected value:
0 does not equal expected value: 50"];

        // uses getter: getMessage
        yield [new Exception('something failed'), 'message', 'something different failed', "'message' property did not have expected value:

--- Expected
+++ Actual
@@ @@
-'something different failed'
+'something failed'
"];

        // uses raw public property: data
        $object = new Exception('something failed');
        $object->data = 'some data';
        yield [$object, 'data', 'some different data', "'data' property did not have expected value:

--- Expected
+++ Actual
@@ @@
-'some different data'
+'some data'
"];
    }

    /**
     * @test
     * @dataProvider objectPropertyValueMatchProvider
     */
    public function hasProperty_generates_callable_that_passes_if_given_object_has_a_property_or_getter_matching_the_given_property_name_and_value(
        $object,
        $propertyName,
        $propertyValueExpected
    ) {
        $propertyCheck = v\hasProperty($propertyName, $propertyValueExpected);
        $propertyCheck($object);
    }

    public function objectPropertyValueMatchProvider()
    {
        // uses getter: getMessage
        yield [new Exception('something failed'), 'message', 'something failed'];

        // uses getter: getCode
        yield [new Exception('something failed', 2001), 'code', 2001];

        // uses raw public property: data
        $object = new Exception('something failed');
        $object->data = 'some data';
        yield [$object, 'data', 'some data'];
    }

    /**
     * @test
     * @dataProvider failingValueAssertionProvider
     */
    public function assert_throws_AssertionFailureException_if_any_validators_fail_when_validating_a_value($valueAssertion, $expectedFailureMessage)
    {
        try {
            v\assert($valueAssertion);
            $this->fail("assert did not throw exception even though the assertion should have failed");
        } catch (v\AssertionFailureException $exception) {
            $this->assertEquals(
                $expectedFailureMessage,
                $exception->getMessage(),
                'Exception thrown, but message was not as expected'
            );
        }
    }

    public function failingValueAssertionProvider()
    {
        yield [
            [
                'that' => new Exception('something failed'),
                'satisfies' => [
                    v\isInstanceOf('RuntimeException'),
                    v\hasProperty('message', 'something failed')
                ],
                'orFailBecause' => 'Exception did not satisfy all validators'
            ],
            "Exception did not satisfy all validators\nGiven object was an instance of Exception instead of RuntimeException as expected"
        ];

        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        yield [
            [
                'that' => $dateTime,
                'satisfies' => [
                    v\isTypeOf('DateTimeInterface'),
                    v\hasProperty('offset', +3600)
                ],
                'orFailBecause' => 'DateTime did not have expected timezone offset'
            ],
            "DateTime did not have expected timezone offset\n'offset' property did not have expected value:\n-14400 does not equal expected value: 3600"
        ];

        yield [
            [
                'that' => $dateTime->getTimezone(),
                'satisfies' => v\hasProperty('name', 'America/Gotham_City'),
                'orFailBecause' => 'DateTime did not have expected TimeZone'
            ],
            "DateTime did not have expected TimeZone\n'name' property did not have expected value:\n\n--- Expected
+++ Actual
@@ @@
-'America/Gotham_City'
+'America/New_York'
"];

        yield [
            [
                'that' => ['foo', 'bar', 'baz'],
                'satisfies' => [
                    v\hasCountOf(4),
                    v\hasValues('foo', 'baz')
                ],
                'orFailBecause' => 'Array was not as expected'
            ],
            "Array was not as expected\nCount was 3 instead of 4 as expected"
        ];
    }

    /**
     * @test
     * @dataProvider passingValueAssertionProvider
     */
    public function assert_does_not_throw_exception_if_no_validators_fail_when_validating_a_value($valueAssertion)
    {
        v\assert($valueAssertion);
    }

    public function passingValueAssertionProvider()
    {
        $someErrorCode = 2001;
        yield [
            [
                'that' => new RuntimeException('something failed', $someErrorCode),
                'satisfies' => [
                    v\isTypeOf('Exception'),
                    v\hasProperty('message', 'something failed'),
                    v\hasProperty('code', $someErrorCode)
                ],
                'orFailBecause' => 'Exception did not satisfy all validators'
            ]
        ];

        $dateTime = new DateTime('now', new DateTimeZone('America/New_York'));
        yield [
            [
                'that' => $dateTime,
                'satisfies' => [
                    v\isTypeOf('DateTimeInterface'),
                    v\hasProperty('offset', -14400)
                ],
                'orFailBecause' => 'DateTime did not have expected timezone offset'
            ]
        ];

        yield [
            [
                'that' => $dateTime->getTimezone(),
                'satisfies' => v\hasProperty('name', 'America/New_York'),
                'orFailBecause' => 'DateTime did not have expected TimeZone'
            ]
        ];

        yield [
            [
                'that' => ['foo', 'bar', 'baz'],
                'satisfies' => [
                    v\hasCountOf(3),
                    v\hasValues('foo', 'baz')
                ],
                'orFailBecause' => 'Array was not as expected'
            ]
        ];
    }

    /**
     * @test
     */
    public function assert_passes_args_to_callable_subject_using_withArgs_key()
    {
        $that = $this;
        $callableWasCalled = false;
        $someArgs = ['bar', 'baz'];
        $someCallable = function ($foo, $biz) use ($that, $someArgs, &$callableWasCalled) {
            $callableWasCalled = true;
            $that->assertEquals($someArgs[0], $foo, '$foo argument was not as expected');
            $that->assertEquals($someArgs[1], $biz, '$biz argument was not as expected');
            throw new Exception();
        };

        v\assert([
            'thatCalling' => $someCallable,
            'withArgs' => $someArgs,
            'throwsExceptionSatisfying' => [],
            'orFailBecause' => 'callable did not throw the expected exception'
        ]);

        $this->assertTrue($callableWasCalled, 'did not call the given callable as expected');
    }

    /**
     * @test
     */
    public function assert_throws_AssertionFailureException_if_exception_is_expected_from_callable_but_no_exception_is_thrown()
    {
        try {
            v\assert([
                'thatCalling' => 'is_int',
                'withArgs' => [5],
                'throwsExceptionSatisfying' => [],
                'orFailBecause' => 'some failure reason'
            ]);
            $this->fail("assert did not throw exception even though the callable never threw an exception");
        } catch (v\AssertionFailureException $exception) {
            $this->assertEquals(
                'The given callable failed to throw an exception',
                $exception->getMessage(),
                'Exception thrown, but message was not as expected'
            );
        }
    }

    /**
     * @test
     * @dataProvider failingCallableAssertionProvider
     */
    public function assert_throws_AssertionFailureException_if_exception_thrown_from_callable_fails_any_of_the_given_validators($assertionData, $expectedFailureMessage)
    {
        try {
            v\assert($assertionData);
            $this->fail("assert did not throw exception even though the exception generated from the callable failed at least 1 validator");
        } catch (v\AssertionFailureException $exception) {
            $this->assertEquals(
                $expectedFailureMessage,
                $exception->getMessage(),
                'Exception thrown, but message was not as expected'
            );
        }
    }

    public function failingCallableAssertionProvider()
    {
        $exceptionThrowningCallable = function () { throw new RuntimeException(); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => v\isInstanceOf('LogicException'),
                'orFailBecause' => 'Thrown exception was not of the expected type'
            ],
            "Thrown exception was not of the expected type\nGiven object was an instance of RuntimeException instead of LogicException as expected"
        ];

        $exceptionThrowningCallable = function () { throw new RuntimeException('something failed'); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => [
                    v\isInstanceOf('RuntimeException'),
                    v\hasProperty('message', 'something different failed')
                ],
                'orFailBecause' => 'Thrown exception was not as expected'
            ],
            "Thrown exception was not as expected\n'message' property did not have expected value:\n\n--- Expected
+++ Actual
@@ @@
-'something different failed'
+'something failed'
"];

        $someErrorCode = 2001;
        $exceptionThrowningCallable = function () use ($someErrorCode) { throw new RuntimeException('something failed', $someErrorCode); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => [
                    v\isInstanceOf('RuntimeException'),
                    v\hasProperty('message', 'something failed'),
                    v\hasProperty('code', 2002)
                ],
                'orFailBecause' => 'Thrown exception was not as expected'
            ],
            "Thrown exception was not as expected\n'code' property did not have expected value:\n2001 does not equal expected value: 2002"
        ];
    }

    /**
     * @test
     * @dataProvider passingCallableAssertionProvider
     */
    public function assert_does_not_throw_exception_if_no_validators_fail_when_validating_exception_thrown_by_callable($callableAssertionData)
    {
        v\assert($callableAssertionData);
    }

    public function passingCallableAssertionProvider()
    {
        $exceptionThrowningCallable = function () { throw new RuntimeException(); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => v\isInstanceOf('RuntimeException'),
                'orFailBecause' => 'Thrown exception was not of the expected type'
            ]
        ];

        $exceptionThrowningCallable = function () { throw new RuntimeException('something failed'); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => [
                    v\isInstanceOf('RuntimeException'),
                    v\hasProperty('message', 'something failed')
                ],
                'orFailBecause' => 'Thrown exception was not as expected'
            ]
        ];

        $someErrorCode = 2001;
        $exceptionThrowningCallable = function () use ($someErrorCode) { throw new RuntimeException('something failed', $someErrorCode); };
        yield [
            [
                'thatCalling' => $exceptionThrowningCallable,
                'throwsExceptionSatisfying' => [
                    v\isInstanceOf('RuntimeException'),
                    v\hasProperty('message', 'something failed'),
                    v\hasProperty('code', $someErrorCode)
                ],
                'orFailBecause' => 'Thrown exception was not as expected'
            ]
        ];
    }
}
