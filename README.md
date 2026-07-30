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
    type: 'com.example.user.created',
    source: 'https://example.com/user-service',
    id: uniqid(),
    subject: 'user-123',
    time: CloudEvent::now(),
    datacontenttype: 'application/json',
    data: [
        'userId' => '123',
        'email' => 'user@example.com',
        'name' => 'John Doe'
    ]
);
```

When using the constructor, only `type`, `source` and `id` are required. All other attributes are optional. Arrays passed to `CloudEvent::fromArray()` must also carry an explicit `specversion`.

`CloudEvent::now()` returns the current time as an RFC 3339 UTC timestamp with millisecond precision (e.g., `2025-11-07T10:00:00.123Z`), ready to use as the `time` attribute.

### Converting to Array

```php
$eventArray = $event->toArray();
// Array with all set CloudEvent attributes; absent optional attributes are omitted
```

### Creating from Array

```php
$eventData = [
    'specversion' => '1.0',
    'type' => 'com.example.user.created',
    'source' => 'https://example.com/user-service',
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

Unknown keys are preserved as extension attributes.

### JSON Event Format

Serialize to and from the [CloudEvents JSON event format](https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/formats/json-format.md), as used by HTTP structured mode and most message brokers:

```php
$json = $event->toJson();

$event = CloudEvent::fromJson($json);
```

Binary payloads (string `data` that is not valid UTF-8) are automatically carried in the `data_base64` member.

### Extension Attributes

Extension attributes carry additional metadata such as distributed tracing context. They are passed at construction time and read directly from the readonly `extensions` property:

```php
$event = new CloudEvent(
    type: 'com.example.user.created',
    source: 'https://example.com/user-service',
    id: uniqid(),
    extensions: [
        'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'sequence' => 42,
    ]
);

$event->extensions['traceparent'] ?? null; // '00-4bf9...'
$event->extensions;                        // all extension attributes
```

Extension names must consist of lowercase letters and digits only, and values must be booleans, integers or strings. These rules are enforced at construction — an invalid extension attribute makes the constructor (and therefore `fromArray()` and `fromJson()`) throw, so a `CloudEvent` instance never carries invalid extensions. `CloudEvent` instances are immutable readonly value objects.

### Validating a CloudEvent

`validate()` checks the event against the spec: the spec version is supported, `type`, `source` and `id` are non-empty, and optional attributes are non-empty when present. Extension attributes are already validated at construction.

```php
try {
    $event->validate();
    echo "Event is valid!";
} catch (InvalidArgumentException $e) {
    echo "Event validation failed: " . $e->getMessage();
}
```

## CloudEvent Properties

The `CloudEvent` class supports the following context attributes according to the CloudEvents v1.0 specification:

- **id** (required): Identifier for the event, unique within the scope of the source
- **source** (required): URI-reference identifying the context in which the event happened (e.g., "https://example.com/user-service")
- **specversion** (required): CloudEvents specification version (default: "1.0")
- **type** (required): Event type identifier, ideally reverse-DNS prefixed (e.g., "com.example.user.created")
- **datacontenttype** (optional): RFC 2046 content type of the data field (e.g., "application/json")
- **dataschema** (optional): URI identifying the schema that data adheres to
- **subject** (optional): Subject of the event in the context of the source
- **time** (optional): Timestamp of when the occurrence happened (RFC 3339 format)
- **data** (optional): Event payload of any type
- **extensions** (optional): Additional extension attributes (e.g., "traceparent")

Optional attributes are omitted from `toArray()` when absent, since the spec does not allow null attribute values. When present, they must not be empty.

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
