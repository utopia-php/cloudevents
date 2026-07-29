# Utopia CloudEvents

[![Tests](https://github.com/utopia-php/cloudevents/actions/workflows/tests.yml/badge.svg)](https://github.com/utopia-php/cloudevents/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/utopia-php/cloudevents.svg)](https://packagist.org/packages/utopia-php/cloudevents)
![Packagist Downloads](https://img.shields.io/packagist/dt/utopia-php/cloudevents.svg)
[![Discord](https://img.shields.io/discord/564160730845151244)](https://appwrite.io/discord)

Utopia CloudEvents is a modern PHP 8.3 implementation of the CloudEvents v1.0 specification. It provides a simple, type-safe way to work with CloudEvents in your PHP applications.

Although part of the [Utopia Framework](https://github.com/utopia-php/framework) family, the library is framework-agnostic and can be used in any PHP project.

## Installation

```bash
composer require utopia-php/cloudevents
```

The library requires PHP 8.3+.

## What are CloudEvents?

CloudEvents is a specification for describing event data in a common way. It provides a standardized format for event producers and consumers to communicate, making it easier to build event-driven architectures.

Learn more at the [CloudEvents specification](https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/spec.md).

## Quick Start

### Creating a CloudEvent

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Utopia\CloudEvents\CloudEvent;

$event = new CloudEvent(
    specversion: '1.0',
    type: 'user.created',
    source: 'user-service',
    subject: 'user-123',
    id: uniqid(),
    time: CloudEvent::now(),
    datacontenttype: 'application/json',
    data: [
        'userId' => '123',
        'email' => 'user@example.com',
        'name' => 'John Doe'
    ],
    dataschema: 'https://example.com/schemas/user.json',
    extensions: ['traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01']
);
```

`CloudEvent::now()` returns an RFC 3339 UTC timestamp with milliseconds
(`2025-11-07T10:00:00.123Z`). Prefer it over `date('c')`, which renders UTC as
`+00:00` and carries no sub-second part.

### Building an event in stages

The object is immutable, so every `with*()` method returns a new instance. This
is handy when the transport assigns the identity of the event — the broker or
log allocates the `id`, and `time` is stamped at publish:

```php
$event = new CloudEvent(type: 'user.created', source: 'user-service');

$published = $event
    ->withId($broker->nextId())
    ->withSubject('user-123')
    ->withData(['userId' => '123'])
    ->withExtension('traceparent', $trace)
    ->withTime(); // defaults to CloudEvent::now()
```

`withSource()` is available too. Passing `null` to `withExtension()` unsets that
extension attribute.

### Converting to Array

```php
$eventArray = $event->toArray();
// Array with all CloudEvent fields
```

### Creating from Array

```php
$eventData = [
    'specversion' => '1.0',
    'type' => 'user.created',
    'source' => 'user-service',
    'subject' => 'user-123',
    'id' => 'unique-id',
    'time' => '2025-11-07T10:00:00Z',
    'datacontenttype' => 'application/json',
    'data' => [
        'userId' => '123',
        'email' => 'user@example.com'
    ]
];

$event = CloudEvent::fromArray($eventData);
```

Any member that is not a spec attribute is carried as an extension attribute, and
`toArray()` emits it again, so a round trip is lossless. Per the JSON format, an
attribute whose value is `null` is treated as unset.

### Strict and lenient decoding

`fromArray()` is strict by default: anything malformed raises
`Utopia\CloudEvents\Exception`. That is what you want when decoding an event from
a peer you control.

When you consume a public stream, one bad optional attribute should not cost you
the whole event. Lenient mode coerces malformed optional attributes to their
default and drops invalid extension attributes:

```php
// A non-string `subject` becomes null instead of raising
$event = CloudEvent::fromArray($raw, lenient: true);

// Also survive a producer that has moved to a spec version this library
// does not know. The version is kept verbatim; validate() still rejects it.
$event = CloudEvent::fromArray($raw, lenient: true, allowUnknownSpecversion: true);
```

Lenient mode never invents a required attribute: a missing `specversion` or a
missing or empty `type` still raises. Neither mode enforces the presence of `id`
and `source` — call `validate()` for a full conformance check.

### Validating a CloudEvent

`validate()` enforces the four REQUIRED context attributes: `id`, `source`,
`specversion` and `type`. `source` is additionally checked against the RFC 3986
URI-reference grammar, so a relative reference such as `user-service` or
`/services/db` passes, while `my service` (unescaped space) and
`http://[invalid]` (malformed authority) do not.

```php
use Utopia\CloudEvents\Exception as CloudEventException;

try {
    $event->validate();
    echo "Event is valid!";
} catch (CloudEventException $e) {
    echo "Event validation failed: " . $e->getMessage();
}
```

Every malformed-input path throws `Utopia\CloudEvents\Exception`, which extends
`InvalidArgumentException`.

## CloudEvent Properties

The `CloudEvent` class supports the following properties according to the CloudEvents v1.0 specification:

- **specversion** (required): CloudEvents specification version (default: "1.0")
- **type** (required): Event type identifier (e.g., "user.created", "v1-stats-usage")
- **source** (required): Context in which the event occurred, a non-empty URI-reference (e.g., service name)
- **id** (required): Unique, non-empty identifier for the event
- **subject** (optional): Subject of the event (e.g., project ID, user ID)
- **time** (optional): Timestamp when the event occurred (RFC 3339 format)
- **datacontenttype** (optional): Content type of the data field (default: "application/json")
- **dataschema** (optional): URI identifying the schema that `data` adheres to
- **data** (optional): Event payload. The JSON format leaves this unrestricted, so an array, string, number, boolean or `null` are all valid.

### Extension attributes

An event may carry any number of extension context attributes, such as
`traceparent`. Names are restricted by the spec to lowercase `a-z` and `0-9`, and
values must be a string, integer or boolean — the CloudEvents type system has no
floating-point type, and its Binary, URI and Timestamp types all serialize as
strings. Anything else raises in strict mode, and is dropped in lenient mode.

```php
$event = $event->withExtension('traceparent', $trace);

$event->getExtension('traceparent');           // the value, or null
$event->getExtension('retrycount', 0);         // with a default
$event->getExtensions();                       // all of them, keyed by name
```

## Use Cases

- **Event-Driven Architecture**: Standardize event formats across microservices
- **Message Queues**: Send CloudEvents via RabbitMQ, Kafka, or other message brokers
- **Webhooks**: Deliver CloudEvents to external systems
- **Event Sourcing**: Store cloudevents in a standardized format
- **Serverless Functions**: Trigger functions with CloudEvents

## Development

- Install dependencies: `composer install`
- Static analysis: `composer check`
- Coding standards: `composer lint` (use `composer format` to auto-fix)
- Tests: `composer test`

## License

MIT
