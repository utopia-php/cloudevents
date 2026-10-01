<?php

namespace Utopia\CloudEvents\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Utopia\CloudEvents\CloudEvent;

class CloudEventTest extends TestCase
{
    /**
     * The smallest event every refusal below starts from: each case changes
     * one attribute, so a refusal can only come from that attribute.
     */
    private const array EVENT = [
        'specversion' => '1.0',
        'type' => 'test.event',
        'source' => 'test-service',
        'id' => 'test-id',
    ];

    private const string JSON = '{"specversion":"1.0","type":"t","source":"s","id":"i"}';

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function malformedArrays(): array
    {
        $without = static fn (string $field): array => \array_diff_key(self::EVENT, [$field => true]);

        return [
            'missing specversion' => [$without('specversion'), 'Missing required field: specversion'],
            'unsupported specversion' => [['specversion' => '2.0'] + self::EVENT, 'Unsupported CloudEvents spec version: 2.0'],
            'missing type' => [$without('type'), 'Missing required field: type'],
            'empty type' => [['type' => ''] + self::EVENT, 'Missing required field: type'],
            'missing source' => [$without('source'), 'Missing required field: source'],
            'empty source' => [['source' => ''] + self::EVENT, 'Missing required field: source'],
            'missing id' => [$without('id'), 'Missing required field: id'],
            'extension name with capitals and underscore' => [self::EVENT + ['Trace_Parent' => 'value'], 'Extension attribute name must contain only lowercase letters and digits'],
            'extension value that is an array' => [self::EVENT + ['myext' => ['nested' => 'array']], 'Extension attribute "myext" must be a boolean, integer or string'],
        ];
    }

    /**
     * @param array<string, mixed> $malformed
     */
    #[DataProvider('malformedArrays')]
    public function testFromArrayRefusesOnlyTheMalformedAttribute(array $malformed, string $message): void
    {
        $this->assertSame(self::EVENT, CloudEvent::fromArray(self::EVENT)->toArray());
        $this->assertRefused(static fn () => CloudEvent::fromArray($malformed), $message);
    }

    /**
     * @return array<string, array{array{specversion?: string, type?: string, source?: string, id?: string, subject?: string, dataschema?: string, datacontenttype?: string}, string}>
     */
    public static function invalidEvents(): array
    {
        return [
            'unsupported specversion' => [['specversion' => '2.0'], 'Unsupported CloudEvents spec version: 2.0'],
            'empty type' => [['type' => ''], 'Event type is required'],
            'empty source' => [['source' => ''], 'Event source is required'],
            'empty id' => [['id' => ''], 'Event id is required'],
            'empty subject' => [['subject' => ''], 'Event subject must not be empty when present'],
            'empty dataschema' => [['dataschema' => ''], 'Event dataschema must not be empty when present'],
            'blank datacontenttype' => [['datacontenttype' => '   '], 'Event datacontenttype must not be empty when present'],
        ];
    }

    /**
     * @param array{specversion?: string, type?: string, source?: string, id?: string, subject?: string, dataschema?: string, datacontenttype?: string} $change
     */
    #[DataProvider('invalidEvents')]
    public function testValidateRefusesOnlyTheInvalidAttribute(array $change, string $message): void
    {
        $this->assertTrue((new CloudEvent(...self::EVENT))->validate());

        $event = new CloudEvent(...($change + self::EVENT));

        $this->assertRefused($event->validate(...), $message);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidExtensions(): array
    {
        return [
            'name with capitals and underscore' => [['Trace_Parent' => 'value'], 'Extension attribute name must contain only lowercase letters and digits'],
            'name of a core attribute' => [['data' => 'value'], 'Extension attribute name conflicts with a core attribute: data'],
            'value that is an array' => [['myext' => ['nested' => 'array']], 'Extension attribute "myext" must be a boolean, integer or string'],
        ];
    }

    /**
     * @param array<string, mixed> $extensions
     */
    #[DataProvider('invalidExtensions')]
    public function testConstructorRefusesOnlyTheInvalidExtension(array $extensions, string $message): void
    {
        $this->assertSame(['traceparent' => 'value'], (new CloudEvent(...self::EVENT, extensions: ['traceparent' => 'value']))->extensions);
        $this->assertRefused(static fn () => new CloudEvent(...self::EVENT, extensions: $extensions), $message);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedJson(): array
    {
        return [
            'invalid JSON' => ['{not json', 'Invalid CloudEvent JSON'],
            'array root' => ['[' . self::JSON . ']', 'CloudEvent JSON must decode to an object'],
            'string root' => ['"just a string"', 'CloudEvent JSON must decode to an object'],
            'data and data_base64' => ['{"specversion":"1.0","type":"t","source":"s","id":"i","data":"x","data_base64":"eA=="}', 'CloudEvent must not contain both data and data_base64'],
            'invalid data_base64' => ['{"specversion":"1.0","type":"t","source":"s","id":"i","data_base64":"!!!not-base64!!!"}', 'data_base64 must be valid Base64'],
        ];
    }

    #[DataProvider('malformedJson')]
    public function testFromJsonRefusesOnlyTheMalformedDocument(string $json, string $message): void
    {
        $this->assertSame('i', CloudEvent::fromJson(self::JSON)->id);
        $this->assertRefused(static fn () => CloudEvent::fromJson($json), $message);
    }

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

    private function assertRefused(callable $call, string $message): void
    {
        try {
            $call();
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("expected the call to be refused with: {$message}");
    }
}
