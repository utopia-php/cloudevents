# Utopia Events

[![Tests](https://github.com/utopia-php/events/actions/workflows/tests.yml/badge.svg)](https://github.com/utopia-php/events/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/utopia-php/events.svg)](https://packagist.org/packages/utopia-php/events)
![Packagist Downloads](https://img.shields.io/packagist/dt/utopia-php/events.svg)
[![Discord](https://img.shields.io/discord/564160730845151244)](https://appwrite.io/discord)

Utopia Events is a modern PHP 8.3 implementation of the CloudEvents v1.0 specification. It provides a simple, type-safe way to work with CloudEvents in your PHP applications.

Although part of the [Utopia Framework](https://github.com/utopia-php/framework) family, the library is framework-agnostic and can be used in any PHP project.

## Installation

```bash
composer require utopia-php/events
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

use Utopia\Event\CloudEvent;

$event = new CloudEvent(
    specversion: '1.0',
    type: 'user.created',
    source: 'user-service',
    subject: 'user-123',
    id: uniqid(),
    time: date('c'),
    datacontenttype: 'application/json',
    data: [
        'userId' => '123',
        'email' => 'user@example.com',
        'name' => 'John Doe'
    ]
);
```

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

### Validating a CloudEvent

```php
try {
    $event->validate();
    echo "Event is valid!";
} catch (InvalidArgumentException $e) {
    echo "Event validation failed: " . $e->getMessage();
}
```

## CloudEvent Properties

The `CloudEvent` class supports the following properties according to the CloudEvents v1.0 specification:

- **specversion** (required): CloudEvents specification version (default: "1.0")
- **type** (required): Event type identifier (e.g., "user.created", "v1-stats-usage")
- **source** (required): Context in which the event occurred (e.g., service name)
- **subject** (optional): Subject of the event (e.g., project ID, user ID)
- **id** (required): Unique identifier for the event
- **time** (required): Timestamp when the event occurred (RFC3339 format)
- **datacontenttype** (optional): Content type of the data field (default: "application/json")
- **data** (required): Event payload as an array

## Use Cases

- **Event-Driven Architecture**: Standardize event formats across microservices
- **Message Queues**: Send CloudEvents via RabbitMQ, Kafka, or other message brokers
- **Webhooks**: Deliver CloudEvents to external systems
- **Event Sourcing**: Store events in a standardized format
- **Serverless Functions**: Trigger functions with CloudEvents

## Development

- Install dependencies: `composer install`
- Static analysis: `composer check`
- Coding standards: `composer lint` (use `composer format` to auto-fix)
- Tests: `composer test`

## License

MIT
