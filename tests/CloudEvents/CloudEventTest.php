<?php

namespace Tests\Unit\CloudEvents;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Utopia\CloudEvents\CloudEvent;

class CloudEventTest extends TestCase
{
    public function testConstructor(): void
    {
        $event = new CloudEvent(
            specversion: '1.0',
            type: 'test.event',
            source: 'test-service',
            subject: 'test-subject',
            id: 'test-id-123',
            time: '2025-11-07T10:00:00Z',
            datacontenttype: 'application/json',
            data: ['key' => 'value']
        );

        $this->assertEquals('1.0', $event->specversion);
        $this->assertEquals('test.event', $event->type);
        $this->assertEquals('test-service', $event->source);
        $this->assertEquals('test-subject', $event->subject);
        $this->assertEquals('test-id-123', $event->id);
        $this->assertEquals('2025-11-07T10:00:00Z', $event->time);
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertEquals(['key' => 'value'], $event->data);
    }

    public function testConstructorWithDefaults(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id'
        );

        $this->assertEquals('1.0', $event->specversion);
        $this->assertEquals('test.event', $event->type);
        $this->assertEquals('test-service', $event->source);
        $this->assertNull($event->subject);
        $this->assertEquals('test-id', $event->id);
        $this->assertNull($event->time);
        $this->assertNull($event->datacontenttype);
        $this->assertNull($event->data);
    }

    public function testFromArray(): void
    {
        $data = [
            'specversion' => '1.0',
            'type' => 'user.created',
            'source' => 'user-service',
            'subject' => 'user-123',
            'id' => 'event-456',
            'time' => '2025-11-07T10:00:00Z',
            'datacontenttype' => 'application/json',
            'data' => ['userId' => '123', 'email' => 'test@example.com']
        ];

        $event = CloudEvent::fromArray($data);

        $this->assertEquals('1.0', $event->specversion);
        $this->assertEquals('user.created', $event->type);
        $this->assertEquals('user-service', $event->source);
        $this->assertEquals('user-123', $event->subject);
        $this->assertEquals('event-456', $event->id);
        $this->assertEquals('2025-11-07T10:00:00Z', $event->time);
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertEquals(['userId' => '123', 'email' => 'test@example.com'], $event->data);
    }

    public function testFromArrayWithMissingOptionalFields(): void
    {
        $data = [
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id'
        ];

        $event = CloudEvent::fromArray($data);

        $this->assertNull($event->subject);
        $this->assertNull($event->time);
        $this->assertNull($event->datacontenttype);
        $this->assertNull($event->data);
    }

    public function testFromArrayMissingSpecversion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: specversion');

        CloudEvent::fromArray([
            'type' => 'test.event',
            'source' => 'test-service'
        ]);
    }

    public function testFromArrayInvalidSpecversion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported CloudEvents spec version: 2.0');

        CloudEvent::fromArray([
            'specversion' => '2.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id'
        ]);
    }

    public function testFromArrayMissingSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: source');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'id' => 'test-id'
        ]);
    }

    public function testFromArrayMissingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: id');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service'
        ]);
    }

    public function testFromArrayEmptySource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: source');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => '',
            'id' => 'test-id'
        ]);
    }

    public function testFromArrayAcceptsZeroStringType(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => '0',
            'source' => 'test-service',
            'id' => 'test-id'
        ]);

        $this->assertEquals('0', $event->type);
    }

    public function testFromArrayMissingType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: type');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'source' => 'test-service'
        ]);
    }

    public function testFromArrayEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: type');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => '',
            'source' => 'test-service'
        ]);
    }

    public function testToArray(): void
    {
        $event = new CloudEvent(
            specversion: '1.0',
            type: 'order.placed',
            source: 'order-service',
            subject: 'order-789',
            id: 'event-abc',
            time: '2025-11-07T10:00:00Z',
            datacontenttype: 'application/json',
            data: ['orderId' => '789', 'amount' => 99.99]
        );

        $array = $event->toArray();

        $this->assertEquals([
            'specversion' => '1.0',
            'type' => 'order.placed',
            'source' => 'order-service',
            'subject' => 'order-789',
            'id' => 'event-abc',
            'time' => '2025-11-07T10:00:00Z',
            'datacontenttype' => 'application/json',
            'data' => ['orderId' => '789', 'amount' => 99.99]
        ], $array);
    }

    public function testToArrayOmitsAbsentOptionalAttributes(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id'
        );

        $array = $event->toArray();

        $this->assertEquals([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id'
        ], $array);
        $this->assertArrayNotHasKey('subject', $array);
        $this->assertArrayNotHasKey('time', $array);
        $this->assertArrayNotHasKey('datacontenttype', $array);
        $this->assertArrayNotHasKey('data', $array);
    }

    public function testDataAcceptsAnyType(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            datacontenttype: 'text/plain',
            data: 'plain text payload'
        );

        $this->assertEquals('plain text payload', $event->data);
        $this->assertEquals('plain text payload', $event->toArray()['data']);

        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'data' => 42
        ]);

        $this->assertEquals(42, $event->data);
    }

    public function testFromArrayDoesNotFabricateDatacontenttype(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id'
        ]);

        $this->assertNull($event->datacontenttype);
        $this->assertArrayNotHasKey('datacontenttype', $event->toArray());
    }

    public function testValidate(): void
    {
        $event = new CloudEvent(
            specversion: '1.0',
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z'
        );

        $this->assertTrue($event->validate());
    }

    public function testValidateInvalidSpecversion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported CloudEvents spec version: 2.0');

        $event = new CloudEvent(
            specversion: '2.0',
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z'
        );

        $event->validate();
    }

    public function testValidateEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type is required');

        $event = new CloudEvent(
            specversion: '1.0',
            type: '',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z'
        );

        $event->validate();
    }

    public function testValidateEmptySource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event source is required');

        $event = new CloudEvent(
            type: 'test.event',
            source: '',
            id: 'test-id'
        );

        $event->validate();
    }

    public function testValidateEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event id is required');

        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: ''
        );

        $event->validate();
    }

    public function testValidateEmptySubject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event subject must not be empty when present');

        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            subject: ''
        );

        $event->validate();
    }

    public function testValidateWithoutTime(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id'
        );

        $this->assertTrue($event->validate());
    }

    public function testRoundTrip(): void
    {
        $original = new CloudEvent(
            specversion: '1.0',
            type: 'payment.processed',
            source: 'payment-service',
            subject: 'payment-xyz',
            id: 'event-123',
            time: '2025-11-07T10:00:00Z',
            datacontenttype: 'application/json',
            data: ['paymentId' => 'xyz', 'status' => 'completed']
        );

        $array = $original->toArray();
        $restored = CloudEvent::fromArray($array);

        $this->assertEquals($original->specversion, $restored->specversion);
        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->source, $restored->source);
        $this->assertEquals($original->subject, $restored->subject);
        $this->assertEquals($original->id, $restored->id);
        $this->assertEquals($original->time, $restored->time);
        $this->assertEquals($original->datacontenttype, $restored->datacontenttype);
        $this->assertEquals($original->data, $restored->data);
    }
}
