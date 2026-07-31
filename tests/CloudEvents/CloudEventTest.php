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
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertNull($event->data);
    }

    public function testDatacontenttypeAllowsExplicitNull(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            datacontenttype: null
        );

        $this->assertNull($event->datacontenttype);
        $this->assertArrayNotHasKey('datacontenttype', $event->toArray());
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
            'id' => 'test-id',
            'datacontenttype' => 'application/json'
        ], $array);
        $this->assertArrayNotHasKey('subject', $array);
        $this->assertArrayNotHasKey('time', $array);
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

    public function testDataschema(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            dataschema: 'https://example.com/schemas/user.json'
        );

        $this->assertEquals('https://example.com/schemas/user.json', $event->dataschema);
        $this->assertEquals('https://example.com/schemas/user.json', $event->toArray()['dataschema']);
        $this->assertTrue($event->validate());

        $restored = CloudEvent::fromArray($event->toArray());
        $this->assertEquals($event->dataschema, $restored->dataschema);
    }

    public function testDataschemaAbsent(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id'
        );

        $this->assertNull($event->dataschema);
        $this->assertArrayNotHasKey('dataschema', $event->toArray());
    }

    public function testValidateEmptyDataschema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event dataschema must not be empty when present');

        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            dataschema: ''
        );

        $event->validate();
    }

    public function testValidateRejectsBlankDatacontenttype(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event datacontenttype must not be empty when present');

        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            datacontenttype: '   '
        );

        $event->validate();
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

    public function testNow(): void
    {
        $time = CloudEvent::now();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $time);
    }

    public function testNowIsValidEventTime(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: CloudEvent::now()
        );

        $this->assertTrue($event->validate());
    }

    public function testExtensions(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: [
                'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
                'sequence' => 42,
                'sampled' => true,
            ]
        );

        $this->assertEquals('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', $event->extensions['traceparent']);
        $this->assertEquals(42, $event->extensions['sequence']);
        $this->assertTrue($event->extensions['sampled']);
        $this->assertTrue($event->validate());
    }

    public function testExtensionsDefaultToEmpty(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id'
        );

        $this->assertEquals([], $event->extensions);
    }

    public function testToArrayIncludesExtensions(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['partitionkey' => 'shard-1']
        );

        $array = $event->toArray();

        $this->assertEquals('shard-1', $array['partitionkey']);
    }

    public function testFromArrayCollectsExtensions(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'traceparent' => '00-abc-def-01',
            'sequence' => 7
        ]);

        $this->assertEquals(['traceparent' => '00-abc-def-01', 'sequence' => 7], $event->extensions);
        $this->assertEquals('00-abc-def-01', $event->extensions['traceparent']);
    }

    public function testFromArrayRejectsInvalidExtensionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension attribute name must contain only lowercase letters and digits');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'Trace_Parent' => 'value'
        ]);
    }

    public function testFromArrayRejectsInvalidExtensionValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension attribute "myext" must be a boolean, integer or string');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'myext' => ['nested' => 'array']
        ]);
    }

    public function testFromArrayDropsNullExtensions(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'traceparent' => null
        ]);

        $this->assertEquals([], $event->extensions);
        $this->assertArrayNotHasKey('traceparent', $event->toArray());
    }

    public function testConstructorRejectsInvalidExtensionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension attribute name must contain only lowercase letters and digits');

        new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['Trace_Parent' => 'value']
        );
    }

    public function testConstructorRejectsReservedExtensionName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension attribute name conflicts with a core attribute: data');

        new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['data' => 'value']
        );
    }

    public function testConstructorRejectsInvalidExtensionValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension attribute "myext" must be a boolean, integer or string');

        new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['myext' => ['nested' => 'array']]
        );
    }

    public function testExtensionRoundTrip(): void
    {
        $original = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['traceparent' => '00-abc-def-01']
        );

        $restored = CloudEvent::fromArray($original->toArray());

        $this->assertEquals($original->extensions, $restored->extensions);
    }

    public function testToJson(): void
    {
        $event = new CloudEvent(
            type: 'user.created',
            source: 'https://example.com/user-service',
            id: 'event-1',
            time: '2025-11-07T10:00:00Z',
            datacontenttype: 'application/json',
            data: ['userId' => '123']
        );

        $decoded = json_decode($event->toJson(), true);

        $this->assertEquals([
            'specversion' => '1.0',
            'type' => 'user.created',
            'source' => 'https://example.com/user-service',
            'id' => 'event-1',
            'time' => '2025-11-07T10:00:00Z',
            'datacontenttype' => 'application/json',
            'data' => ['userId' => '123']
        ], $decoded);
    }

    public function testFromJson(): void
    {
        $json = '{"specversion":"1.0","type":"user.created","source":"user-service","id":"event-1","data":{"userId":"123"},"traceparent":"00-abc-def-01"}';

        $event = CloudEvent::fromJson($json);

        $this->assertEquals('user.created', $event->type);
        $this->assertEquals((object) ['userId' => '123'], $event->data);
        $this->assertEquals('00-abc-def-01', $event->extensions['traceparent']);
    }

    public function testFromJsonPreservesJsonDataTypes(): void
    {
        $json = '{"specversion":"1.0","type":"t","source":"s","id":"i","data":{"empty":{},"list":[]}}';

        $restored = CloudEvent::fromJson($json)->toJson();

        $this->assertStringContainsString('"empty":{}', $restored);
        $this->assertStringContainsString('"list":[]', $restored);
    }

    public function testFromJsonRejectsArrayRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CloudEvent JSON must decode to an object');

        CloudEvent::fromJson('[{"specversion":"1.0","type":"t","source":"s","id":"i"}]');
    }

    public function testFromJsonInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CloudEvent JSON');

        CloudEvent::fromJson('{not json');
    }

    public function testFromJsonNonObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CloudEvent JSON must decode to an object');

        CloudEvent::fromJson('"just a string"');
    }

    public function testJsonBinaryDataRoundTrip(): void
    {
        $binary = "\x89PNG\r\n\x1a\n\x00\x01\x02\x80\xff";

        $event = new CloudEvent(
            type: 'image.uploaded',
            source: 'storage',
            id: 'event-1',
            datacontenttype: 'image/png',
            data: $binary
        );

        $decoded = json_decode($event->toJson(), true);

        $this->assertArrayNotHasKey('data', $decoded);
        $this->assertEquals(base64_encode($binary), $decoded['data_base64']);

        $restored = CloudEvent::fromJson($event->toJson());

        $this->assertEquals($binary, $restored->data);
    }

    public function testFromJsonRejectsDataAndDataBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CloudEvent must not contain both data and data_base64');

        CloudEvent::fromJson('{"specversion":"1.0","type":"t","source":"s","id":"i","data":"x","data_base64":"eA=="}');
    }

    public function testFromJsonRejectsInvalidBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('data_base64 must be valid Base64');

        CloudEvent::fromJson('{"specversion":"1.0","type":"t","source":"s","id":"i","data_base64":"!!!not-base64!!!"}');
    }

    public function testJsonRoundTrip(): void
    {
        $original = new CloudEvent(
            type: 'payment.processed',
            source: 'https://example.com/payments',
            id: 'event-123',
            subject: 'payment-xyz',
            time: '2025-11-07T10:00:00Z',
            datacontenttype: 'application/json',
            dataschema: 'https://example.com/schemas/payment.json',
            data: ['paymentId' => 'xyz'],
            extensions: ['traceparent' => '00-abc-def-01']
        );

        $restored = CloudEvent::fromJson($original->toJson());

        $this->assertEquals($original->type, $restored->type);
        $this->assertEquals($original->source, $restored->source);
        $this->assertEquals($original->id, $restored->id);
        $this->assertEquals($original->subject, $restored->subject);
        $this->assertEquals($original->time, $restored->time);
        $this->assertEquals($original->datacontenttype, $restored->datacontenttype);
        $this->assertEquals($original->dataschema, $restored->dataschema);
        $this->assertEquals($original->extensions, $restored->extensions);
        $this->assertJsonStringEqualsJsonString($original->toJson(), $restored->toJson());
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
