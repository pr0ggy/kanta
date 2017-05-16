## Kanta
### An easy-to-use, exception-based assertion library for PHP 5.6 and later

## Installation
    composer require --dev pr0ggy/kanta

## Assertion API
Kanta has 1 aim: provide a succinct, readable assertion API that is as descriptive and readable as possible while being as lightweight as possible.  Kanta has a single `assert` function that takes a single argument: a dictionary describing an assertion.  If the assertion fails, an instance of `Kanta\Validation\AssertionFailureException` will be thrown with a message explaining why the assertion failed.  There are 2 main assertion types: assertions about a value/array/object, or assertions about an exception that is expected to be thrown from a given callable when called with a given set of arguments (or no arguments, as the case may be).

### Asserting on Values/Objects/Arrays
```php
<?php

use Kanta\Validation as v;

// ...

    v\assert([
        'that' => new RuntimeException('something failed', 5001),
        'satisfies' => [
            v\isTypeOf('Exception'),
            v\hasProperty('message', 'something failed'),
            v\hasProperty('code', 5001)
        ],
        'orFailBecause' => 'The created exception was not as expected'
    ]);

// ...
```
Assertions made about values/objects/arrays are described by 3 keys:

- **that**: Defines the subject of validation
- **satisfies**: Defines a single validator callable or a list of such callables that will receive the subject of validation as an argument and either return successfully or throw an instance of Kanta\Validation\ValidationFailureException.  The assertion fails if any of these validators fail.
- **orFailBecause**: Defines a summary of the reason why the assertion would fail

### Asserting on Expected Exceptions Thrown By Callables
```php
<?php

use Kanta\Validation as v;

// ...

    v\assert([
        'thatCalling' => [$someObject, 'someMethod'],
        'withArgs' => ['foo'],
        'throwsExceptionSatisfying' => [
            v\isTypeOf('Exception'),
            v\hasProperty('message', 'something failed')
        ],
        'orFailBecause' => 'Calling subject::someMethod("foo") did not throw the expected exception'
    ]);

// ...
```
Assertions made about exceptions thrown by callables are described by the following keys:

- **thatCalling**: Defines the callable subject of validation
- **withArgs**: (*optional*) Defines arguments to pass to the callable during the test
- **throwsExceptionSatisfying**: Defines a single validator callable or a list of such callables that will receive the exception expected to be thrown by the callable as an argument and either return successfully or throw an instance of Kanta\Validation\ValidationFailureException.  The assertion fails if any of these validators fail, or if the callable subject fails to throw an exception.
- **orFailBecause**: Defines a summary of the reason why the assertion would fail

## Validators
As stated above, validators are merely callables passed in with the assertion data that will accept the subject of the assertion as an argument and either return successfully or throw an instance of `Kanta\Validation\ValidationFailureException`.  Kanta ships with a number of built-in validator generator functions within the `Kanta\Validation` namespace that should cover a broad range of assertion needs:

#### is($referenceValue)
Fails if the subject of validation is not strictly equal (===) to the given reference value
#### isInstanceOf($expectedClassName)
Fails if the subject of validation is not an instance of the given class name.  Note that the class name must match exactly...subclasses will not pass.
#### isTypeOf($typeNameExpected)
Fails if the subject of validation does not match the given type (class or interface).  Unlike `isInstanceOf()`, interface names are allowed and subclasses of a given expected class name will pass.
#### isObject()
Fails if the subject of validation is not an object
#### hasKeys(...$keysExpected)
Fails if the subject of validation does not have the specified keys (or if the subject is not a valid key/value store)
#### hasKVPair($key, $value)
Fails if the subject of validation does not have the specified key/value pair (or if the subject is not a valid key/value store)
#### hasValues(...$valuesExpected)
Fails if the subject of validation is not an array or Traversable instance with the given values
#### hasCountOf($n)
Fails if the subject of validation is not an array or Countable instance with the given item count
#### hasProperty($propertyName, $propertyValueExpected = null)
Fails if the subject of validation is not an object containing the given property.  If the value expected argument is non-null, validation will fail if the value of the property is not strictly equal.  Note that this method will first check for the existence of a property getter in the format `get<PropertyName>` before attempting to access the property directly.

## Explicitly Passing/Failing a Test
In some tests, scenarios arise where it makes sense to pass/fail explicitly, so functions are available for  within the `Kanta\Validation` namespace:
#### pass()
A no-op...because Kanta is exception-based, this function is just for readability within a given test case.
#### fail($message)
Explicitly throws a `Kanta\Validation\AssertionFailureException` instance with the given message.

## Testing Kanta
    ./vendor/bin/phpunit

## License
**GPL-3**
