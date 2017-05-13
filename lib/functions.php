<?php

namespace Kanta\Validation;

use Exception;
use Traversable;
use RuntimeException;
use Kanta\Validation\AssertionFailureException;
use Kanta\Validation\ValidationFailureException;
use SebastianBergmann\Comparator;
use SebastianBergmann\Exporter\Exporter;

// -------------------------------------------------------------------------------------------------
// ----- EXPECTED ASSERTION DATA KEYS --------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
const VALUE_SUBJECT_KEY = 'that';
const VALUE_VALIDATOR_KEY = 'satisfies';
const CALLABLE_SUBJECT_KEY = 'thatCalling';
const CALLABLE_SUBJECT_ARGS_KEY = 'withArgs';
const CALLABLE_EXCEPTION_VALIDATOR_KEY = 'throwsExceptionSatisfying';
const FAILURE_REASON_KEY = 'orFailBecause';

// -------------------------------------------------------------------------------------------------
// ----- POTENTIAL ASSERTION DATA ERRORS -----------------------------------------------------------
// -------------------------------------------------------------------------------------------------
const NO_SUBJECT_SPECIFIED_ERROR = "Must include a subject to test when passing assertion setup to the assert function.
For testing values/objects/arrays, use the '".VALUE_SUBJECT_KEY."' key
For testing that a callable throws an exception, use the '".CALLABLE_SUBJECT_KEY."' key.";

const MULTIPLE_SUBJECTS_SPECIFIED_ERROR = "Must specify only a single key denoting the subject to test when passing assertion setup to the assert function.
For testing values/objects/arrays, use the '".VALUE_SUBJECT_KEY."' key
For testing that a callable throws an exception, use the '".CALLABLE_SUBJECT_KEY."' key.";

const MISSING_FAILURE_REASON_ERROR = 'Must specify a string containing the failure reason summary within the "'.FAILURE_REASON_KEY.'" key when passing assertion setup to the assert function.';

const MISSING_VALUE_SUBJECT_VALIDATOR_ERROR = 'Must specify which validators to test the given subject against using the "'.VALUE_VALIDATOR_KEY.'" key when passing assertion setup to the assert function.';

const VALUE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR = 'Must pass a callable or an array of callables using the "'.VALUE_VALIDATOR_KEY.'" key to include validation callbacks when passing assertion setup to the assert function.';

const MISSING_CALLABLE_SUBJECT_VALIDATOR_ERROR = 'Must specify which validators to test the expected exception against using the "'.CALLABLE_EXCEPTION_VALIDATOR_KEY.'" key when passing assertion setup to the assert function.';

const CALLABLE_SUBJECT_IS_NOT_CALLABLE_ERROR = "The subject given via the '".CALLABLE_SUBJECT_KEY."' key must be callable.";

const CALLABLE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR = 'Must pass a callable or an array of callables using the "'.CALLABLE_EXCEPTION_VALIDATOR_KEY.'" key to include validation callbacks when passing assertion setup to the assert function.';

// -------------------------------------------------------------------------------------------------
// ----- LIBRARY FUNCTIONS -------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------

/**
 * interface function
 * the main assertion method used to execute entity or callable validation in Kanta
 *
 * @param  array $assertionData  data describing the assertion to execute
 * @throws Kanta\Validation\AssertionFailureException if the assertion fails
 */
function assert(array $assertionData)
{
    _verifyAssertionDataStructure($assertionData);

    if (_isValueAssertion($assertionData)) {
        _executeValueAssertion($assertionData);
    } elseif (_isThrowingCallableAssertion($assertionData)) {
        _executeThrowingCallableAssertion($assertionData);
    }
}

/**
 * internal function
 * verifies the integrity of an assertion data structure given to the assert function
 *
 * @param  array  $assertionData  data describing the assertion to execute
 * @throws RuntimeException  if any issues are found with the structure of the given assertion
 */
function _verifyAssertionDataStructure(array $assertionData)
{
    $specifiedDataKeys = array_keys($assertionData);

    // ----- ENSURE A SINGLE SUBJECT OF VALIDATION IS SPECIFIED ------------------------------------
    $valueSubjectKeyIsPresent = in_array(VALUE_SUBJECT_KEY, $specifiedDataKeys);
    $callableSubjectKeyIsPresent = in_array(CALLABLE_SUBJECT_KEY, $specifiedDataKeys);

    if ($valueSubjectKeyIsPresent === false && $callableSubjectKeyIsPresent === false) {
        throw new RuntimeException(NO_SUBJECT_SPECIFIED_ERROR);
    }

    if ($valueSubjectKeyIsPresent && $callableSubjectKeyIsPresent) {
        throw new RuntimeException(MULTIPLE_SUBJECTS_SPECIFIED_ERROR);
    }

    // ----- ENSURE A FAILURE REASON IS SPECIFIED --------------------------------------------------
    if (in_array(FAILURE_REASON_KEY, $specifiedDataKeys) === false) {
        throw new RuntimeException(MISSING_FAILURE_REASON_ERROR);
    }

    if (_isValueAssertion($assertionData)) {
        // ----- ENSURE VALIDATORS ARE GIVEN -------------------------------------------------------
        if (in_array(VALUE_VALIDATOR_KEY, $specifiedDataKeys) === false) {
            throw new RuntimeException(MISSING_VALUE_SUBJECT_VALIDATOR_ERROR);
        }

        // ----- ENSURE ALL GIVEN VALIDATORS ARE CALLABLE ------------------------------------------
        foreach (_toTraversable($assertionData[VALUE_VALIDATOR_KEY]) as $validationCallback) {
            if (is_callable($validationCallback) === false) {
                throw new RuntimeException(VALUE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR);
            }
        }
    } elseif (_isThrowingCallableAssertion($assertionData)) {
        // ----- ENSURE SUBJECT IS CALLABLE --------------------------------------------------------
        if (is_callable($assertionData[CALLABLE_SUBJECT_KEY]) === false) {
            throw new RuntimeException(CALLABLE_SUBJECT_IS_NOT_CALLABLE_ERROR);
        }

        // ----- ENSURE VALIDATORS ARE GIVEN -------------------------------------------------------
        if (in_array(CALLABLE_EXCEPTION_VALIDATOR_KEY, $specifiedDataKeys) === false) {
            throw new RuntimeException(MISSING_CALLABLE_SUBJECT_VALIDATOR_ERROR);
        }

        // ----- ENSURE ALL GIVEN VALIDATORS ARE CALLABLE ------------------------------------------
        foreach (_toTraversable($assertionData[CALLABLE_EXCEPTION_VALIDATOR_KEY]) as $validationCallback) {
            if (is_callable($validationCallback) === false) {
                throw new RuntimeException(CALLABLE_SUBJECT_VALIDATOR_CANNOT_BE_CALLED_ERROR);
            }
        }
    }
}

/**
 * internal function
 *
 * @param  array   $assertionData  data describing the assertion to execute
 * @return boolean  true if the given assertion data represents a value assertion (an assertion that
 *                  runs validator functions against a known value rather than calling a callable
 *                  and running validators on the expected thrown exception)
 */
function _isValueAssertion(array $assertionData)
{
    return array_key_exists(VALUE_SUBJECT_KEY, $assertionData);
}

/**
 * internal function
 * executes a value assertion (an assertion that runs validator functions against a known value
 * rather than calling a callable and running validators on the expected thrown exception)
 *
 * @param  array  $assertionData  data describing the assertion to execute
 * @throws Kanta\Validation\AssertionFailureException  if any of the assertion validation functions
 *                                                    fail
 */
function _executeValueAssertion(array $assertionData)
{
    $validators = _toTraversable($assertionData[VALUE_VALIDATOR_KEY]);
    $subject = $assertionData[VALUE_SUBJECT_KEY];

    try {
        foreach ($validators as $validator) {
            $validator($subject);
        }
    } catch (ValidationFailureException $exception) {
        $failureReasonSummary = $assertionData[FAILURE_REASON_KEY];
        $failureException = new AssertionFailureException(
            $failureReasonSummary.PHP_EOL.
            $exception->getMessage().
            _failureFileAndLine($exception)
        );
        $failureException->data = (property_exists($exception, 'data') ? $exception->data : null);
        throw $failureException;
    }
}

/**
 * internal function
 *
 * @param  mixed $value
 * @return mixed  the given value if it is already traversable, otherwise returns the value wrapped
 *                in an array
 */
function _toTraversable($value)
{
    if (is_array($value) || $value instanceof Traversable) {
        return $value;
    }

    return [$value];
}

/**
 * internal function
 *
 * @param  ValidationFailureException $exception
 * @return string  the top file and line in the trace stack of the given exception that is not within
 * the Kanta project, as this should be the line in the actual test file containing the assertion that
 * failed
 */
function _failureFileAndLine(ValidationFailureException $exception)
{
    $failureFileAndLine = '';
    foreach ($exception->getTrace() as $stackLevel) {
        if (array_key_exists('file', $stackLevel) && strpos($stackLevel['file'], '/kanta/') === false) {
            $failureFileAndLine = PHP_EOL."Line {$stackLevel['line']} of {$stackLevel['file']}";
            break;
        }
    }

    return $failureFileAndLine;
}

/**
 * internal function
 *
 * @param  array   $assertionData  data describing the assertion to execute
 * @return boolean  true if the given assertion data represents a callable assertion (an assertion
 *                  that a given callable throws an exception which satisfies given validators
 *                  rather than running validators on a known value)
 */
function _isThrowingCallableAssertion(array $assertionData)
{
    return array_key_exists(CALLABLE_SUBJECT_KEY, $assertionData);
}

/**
 * internal function
 * executes a callable assertion (an assertion that a given callable throws an exception which
 * satisfies given validators rather than running validators on a known value)
 *
 * @param  array  $assertionData  data describing the assertion to execute
 * @throws Kanta\Validation\AssertionFailureException  if any of the assertion validation functions
 *                                                    fail
 */
function _executeThrowingCallableAssertion(array $assertionData)
{
    $callable = $assertionData[CALLABLE_SUBJECT_KEY];
    $args = (isset($assertionData[CALLABLE_SUBJECT_ARGS_KEY]) ? $assertionData[CALLABLE_SUBJECT_ARGS_KEY] : []);
    $validators = _toTraversable($assertionData[CALLABLE_EXCEPTION_VALIDATOR_KEY]);
    $callableFailedToThrow = false;

    try {
        $callable(...$args);
        $callableFailedToThrow = true;
    } catch (Exception $subject) {
        // LET ANY PHPUNIT EXCEPTIONS SLIDE THROUGH
        if (strpos(get_class($subject), 'PHPUnit') !== false) {
            throw $subject;
        }

        try {
            foreach ($validators as $validator) {
                $validator($subject);
            }
        } catch (ValidationFailureException $exception) {
            $failureReasonSummary = $assertionData[FAILURE_REASON_KEY];
            $failureException = new AssertionFailureException(
                $failureReasonSummary.PHP_EOL.
                $exception->getMessage().
                _failureFileAndLine($exception)
            );
            $failureException->data = (property_exists($exception, 'data') ? $exception->data : null);

            throw $failureException;
        }
    }

    if ($callableFailedToThrow) {
        fail('The given callable failed to throw an exception');
    }
}

/**
 * interface function
 * simple readability function, a no-op as Kanta is assertion-based so not throwing indicates a pass
 */
function pass()
{
    // no-op
}

/**
 * interface function
 * explicitly fails the test case in which this function is called by throwing an instance of
 * Kanta\Validation\AssertionFailureException
 *
 * @param  string $message  the message describing the cause of the failure
 * @throws Kanta\Validation\AssertionFailureException
 */
function fail($message = 'Test explicitly failed (This message should ideally be more descriptive...)')
{
    throw new AssertionFailureException($message);
}

/**
 * internal function
 * Creates a string diff between 2 given entities.  The comparison is made using strict equality
 * (===).  If the 2 given values exhibit strict equality, NULL is returned.  Otherwise, a string
 * diff is returned that gives a summary of the difference between the given entities.
 *
 * @param  mixed $valueExpected  the expected entity
 * @param  mixed $value          the actual entity
 * @return null|string  null if the expected\actual entities exhibit strict equality, or a string
 *                      representation of the difference between the entities if they do not.
 */
function _getActualExpectedDiff($valueExpected, $value)
{
    if ($valueExpected === $value) {
        return;
    }

    $expectedValueType = gettype($valueExpected);
    $actualValueType = gettype($value);
    $types = [$expectedValueType, $actualValueType];
    $expectedTypeDiffersFromActual = (count(array_unique($types)) !== 1);

    if ($expectedTypeDiffersFromActual) {
        return "expected type {$expectedValueType} differs from received type: {$actualValueType}";
    } else {
        try {
            (new Comparator\Factory())
                ->getComparatorFor($valueExpected, $value)
                ->assertEquals($valueExpected, $value);
        } catch (Comparator\ComparisonFailure $failure) {
            $diff = $failure->getDiff();
            if ($diff === '') {
                // if an empty string is returned from the Comparator diff, it means the Comparator
                // didn't know how to compare the given values...try a raw string comparison as last
                // ditch effort
                $diff = _asString($value).' does not equal expected value: '._asString($valueExpected);
            }

            return $diff;
        }
    }
}

/**
 * internal function
 *
 * @param  mixed $entity
 * @return string  a string representation of the given entity
 */
function _asString($entity)
{
    static $exporter;

    if (is_object($entity) || is_array($entity)) {
        return gettype($entity);
    }

    if (isset($exporter) === false) {
        $exporter = new Exporter();
    }
    return $exporter->export($entity);
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it does not exhibit strict
 * (===) equality with the given reference entity
 *
 * @param  mixed  $referenceEntity
 * @return callable
 */
function is($referenceEntity)
{
    return function ($entity) use ($referenceEntity) {
        $diff = _getActualExpectedDiff($referenceEntity, $entity);
        if ($diff) {
            throw new ValidationFailureException($diff);
        }
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an instance of the
 * specified class
 *
 * @param  string  $expectedClassName
 * @return callable
 */
function isInstanceOf($expectedClassName)
{
    return function ($object) use ($expectedClassName) {
        isObject()($object);
        $objectClass = get_class($object);
        if ($expectedClassName === $objectClass) {
            return;
        }

        throw new ValidationFailureException("Given object was an instance of {$objectClass} instead of {$expectedClassName} as expected");
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an object
 *
 * @return callable
 */
function isObject()
{
    return function ($entity) {
        if (is_object($entity)) {
            return;
        }

        throw new ValidationFailureException('Given entity was not an object: '._asString($entity));
    };
}

/**
 * interface function
 * Validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an instance of the
 * specified type.  Note that class names and interface names may be specified; subclasses of a
 * given class will pass validation, instances implementing the given interface will pass validtion,
 * etc.
 *
 * @param  string  $typeNameExpected
 * @return callable
 */
function isTypeOf($typeNameExpected)
{
    return function ($object) use ($typeNameExpected) {
        isObject()($object);
        if ($object instanceof $typeNameExpected) {
            return;
        }

        $objectClass = get_class($object);
        throw new ValidationFailureException("Given object was instance of {$objectClass}, which is not a type of {$typeNameExpected}");
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an Interable
 * containing the specified keys
 *
 * @param  mixed[]  ...$keysExpected
 * @return callable
 */
function hasKeys(...$keysExpected)
{
    return function ($entity) use ($keysExpected) {
        isTraversable()($entity);
        $entityKeys = [];
        foreach ($entity as $entityKey => $value) {
            $entityKeys[] = $entityKey;
        }

        $missingKeys = array_values(array_diff($keysExpected, $entityKeys));
        if (empty($missingKeys)) {
            return;
        }

        throw new ValidationFailureException("Key not found: {$missingKeys[0]}");
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an array or an
 *                                            instance of the Traversable interface
 *
 * @return callable
 */
function isTraversable()
{
    return function ($entity) {
        if (is_array($entity) || $entity instanceof Traversable) {
            return;
        }

        throw new ValidationFailureException('Given entity was not traversable: '._asString($entity));
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an Interable
 * containing the specified key/value pair
 *
 * @param  mixed  $key
 * @param  mixed  $value
 * @return callable
 */
function hasKVPair($key, $value)
{
    return function ($entity) use ($key, $value) {
        hasKeys($key)($entity);
        try {
            is($value)($entity[$key]);
        } catch (ValidationFailureException $failure) {
            throw new ValidationFailureException('Key exists, but value was not as expected:'.PHP_EOL.$failure->getMessage());
        }
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an Interable
 * containing the specified key/value pair
 *
 * @param  mixed[] ...$valuesExpected
 * @return callable
 */
function hasValues(...$valuesExpected)
{
    return function ($entity) use ($valuesExpected) {
        isTraversable()($entity);
        $entityValues = [];
        foreach ($entity as $value) {
            $entityValues[] = $value;
        }
        foreach ($valuesExpected as $expectedValue) {
            if (in_array($expectedValue, $entityValues, true) === false) {
                throw new ValidationFailureException('Expected value not found: '._asString($expectedValue));
            }
        }
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it is not an instance of
 * Countable with the given count
 *
 * @param  integer $n
 * @return callable
 */
function hasCountOf($n)
{
    return function ($countable) use ($n) {
        $count = count($countable);
        if ($count === $n) {
            return;
        }

        throw new ValidationFailureException("Count was {$count} instead of {$n} as expected");
    };
}

/**
 * interface function
 * validator generation function which returns a callback that throws an instance of
 * Kanta\Validation\ValidationFailureException if the argument passed to it does not have a property
 * with the given name.  If a value other than null is given as the second argument, validation will
 * fail unless the property is strictly equal (===) to the given expected value.  Note that the
 * validation callback returned will automatically attempt to call a getter of the format
 * get<PropertyName> first before attempting to access the property directly.
 *
 * @param  string $propertyName
 * @param  mixed $propertyValueExpected
 * @return callable
 */
function hasProperty($propertyName, $propertyValueExpected = null)
{
    return function ($object) use ($propertyName, $propertyValueExpected) {
        isObject()($object);
        $potentialGetterMethod = [$object, 'get'.ucfirst($propertyName)];

        if (property_exists($object, $propertyName) === false && is_callable($potentialGetterMethod) === false) {
            throw new ValidationFailureException("No property found with the given name: {$propertyName}");
        }

        if (isset($propertyValueExpected) === false) {
            return;
        }

        if (is_callable($potentialGetterMethod)) {
            $propertyValue = $potentialGetterMethod();
        } else {
            $propertyValue = $object->$propertyName;
        }

        try {
            is($propertyValueExpected)($propertyValue);
        } catch (ValidationFailureException $failure) {
            throw new ValidationFailureException("'$propertyName' property did not have expected value:".PHP_EOL.$failure->getMessage());
        }
    };
}
