<?php

namespace Tests\Unit\CloudEvents;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Utopia\CloudEvents\CloudEvent;
use Utopia\CloudEvents\Exception as CloudEventException;

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
        $event = new CloudEvent();

        $this->assertEquals('1.0', $event->specversion);
        $this->assertEquals('', $event->type);
        $this->assertEquals('', $event->source);
        $this->assertNull($event->subject);
        $this->assertEquals('', $event->id);
        $this->assertEquals('', $event->time);
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertEquals([], $event->data);
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
            'id' => 'test-id',
            'time' => '2025-11-07T10:00:00Z'
        ];

        $event = CloudEvent::fromArray($data);

        $this->assertNull($event->subject);
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertEquals([], $event->data);
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
            'source' => 'test-service'
        ]);
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
            'dataschema' => null,
            'data' => ['orderId' => '789', 'amount' => 99.99]
        ], $array);
    }

    public function testToArrayWithNullSubject(): void
    {
        $event = new CloudEvent(
            specversion: '1.0',
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z'
        );

        $array = $event->toArray();

        $this->assertNull($array['subject']);
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

    /**
     * Item 1: sparse but valid input must not crash.
     */
    public function testFromArrayWithSparseInput(): void
    {
        $event = CloudEvent::fromArray(['specversion' => '1.0', 'type' => 'x']);

        $this->assertEquals('1.0', $event->specversion);
        $this->assertEquals('x', $event->type);
        $this->assertEquals('', $event->source);
        $this->assertEquals('', $event->id);
        $this->assertEquals('', $event->time);
        $this->assertNull($event->subject);
        $this->assertNull($event->dataschema);
        $this->assertEquals('application/json', $event->datacontenttype);
        $this->assertEquals([], $event->data);
    }

    public function testFromArrayWithNullAttributes(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'x',
            'source' => null,
            'id' => null,
            'time' => null,
            'subject' => null,
            'datacontenttype' => null,
            'dataschema' => null,
        ]);

        $this->assertEquals('', $event->source);
        $this->assertEquals('', $event->id);
        $this->assertEquals('', $event->time);
        $this->assertNull($event->subject);
        $this->assertNull($event->dataschema);
        $this->assertEquals('application/json', $event->datacontenttype);
    }

    public function testFromArrayThrowsLibraryException(): void
    {
        try {
            CloudEvent::fromArray(['specversion' => '1.0', 'type' => 'x', 'source' => 123]);
            $this->fail('Expected a CloudEvents exception');
        } catch (CloudEventException $e) {
            $this->assertEquals('Attribute "source" must be a string', $e->getMessage());
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }

    public function testFromArrayNonStringSpecversion(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Attribute "specversion" must be a string');

        CloudEvent::fromArray(['specversion' => 1.0, 'type' => 'x']);
    }

    public function testFromArrayNonStringType(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Attribute "type" must be a string');

        CloudEvent::fromArray(['specversion' => '1.0', 'type' => ['x']]);
    }

    /**
     * Item 2: all four REQUIRED attributes are enforced.
     */
    public function testValidateMissingId(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Event id is required');

        (new CloudEvent(type: 'test.event', source: 'test-service'))->validate();
    }

    public function testValidateMissingSource(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Event source is required');

        (new CloudEvent(type: 'test.event', id: 'test-id'))->validate();
    }

    public function testValidateRejectsEventDecodedFromSparseInput(): void
    {
        $event = CloudEvent::fromArray(['specversion' => '1.0', 'type' => 'x']);

        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Event id is required');

        $event->validate();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validSourceProvider(): array
    {
        return [
            'relative path' => ['/services/db'],
            'relative reference' => ['user-service'],
            'absolute uri' => ['https://github.com/cloudevents/spec/pull/123'],
            'urn' => ['urn:uuid:6e8bc430-9c3a-11d9-9669-0800200c9a66'],
            'percent encoded' => ['/services/my%20service'],
            'query and fragment' => ['/services/db?tenant=1#events'],
            'network path reference' => ['//example.com/path'],
            'userinfo and port' => ['http://user:pw@example.com:8080/a/b?q=1#f'],
            'ipv4 host' => ['http://192.168.0.1/events'],
            'ipv6 literal' => ['http://[2001:db8::1]:8080/events'],
            'ipvfuture literal' => ['http://[v7.fe80::a+en1]/events'],
            'mailto' => ['mailto:events@example.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validSourceProvider')]
    public function testValidateAcceptsUriReferenceSource(string $source): void
    {
        $event = new CloudEvent(type: 'test.event', source: $source, id: 'test-id');

        $this->assertTrue($event->validate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSourceProvider(): array
    {
        return [
            'unescaped space' => ['my service'],
            'control character' => ["test\nservice"],
            'raw non-ascii' => ['/services/café'],
            'truncated percent escape' => ['/services/my%2'],
            'invalid percent escape' => ['/services/my%zz'],
            'angle brackets' => ['<test-service>'],
            'trailing newline' => ["test-service\n"],
            // Structurally malformed, even though every character is allowed
            'malformed ip literal' => ['http://[invalid]'],
            'unterminated ip literal' => ['http://[fe80::1'],
            'ipv4 in brackets' => ['http://[192.168.0.1]'],
            'brackets outside authority' => ['/services/[db]'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSourceProvider')]
    public function testValidateRejectsMalformedSource(string $source): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Event source must be a valid URI-reference');

        (new CloudEvent(type: 'test.event', source: $source, id: 'test-id'))->validate();
    }

    public function testValidateAcceptsAllFourRequiredAttributes(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: '/services/test',
            id: '0'
        );

        $this->assertTrue($event->validate());
    }

    /**
     * Item 3: `data` is unrestricted.
     *
     * @return array<string, array{mixed}>
     */
    public static function dataPayloadProvider(): array
    {
        return [
            'object' => [['key' => 'value']],
            'list' => [['a', 'b', 'c']],
            'nested list' => [[['id' => 1], ['id' => 2]]],
            'string' => ['plain text'],
            'integer' => [42],
            'float' => [99.99],
            'boolean' => [true],
            'null' => [null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataPayloadProvider')]
    public function testDataPayloadRoundTrip(mixed $data): void
    {
        $original = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z',
            data: $data
        );

        $this->assertSame($data, $original->data);

        $restored = CloudEvent::fromArray($original->toArray());

        $this->assertSame($data, $restored->data);
    }

    public function testDataDefaultsToEmptyArrayWhenAbsent(): void
    {
        $event = CloudEvent::fromArray(['specversion' => '1.0', 'type' => 'x']);

        $this->assertSame([], $event->data);
    }

    /**
     * Item 4: extension attributes are carried.
     */
    public function testFromArrayCarriesExtensions(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
            'retrycount' => 3,
            'sampled' => true,
        ]);

        $this->assertEquals([
            'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
            'retrycount' => 3,
            'sampled' => true,
        ], $event->getExtensions());

        $this->assertEquals('00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01', $event->getExtension('traceparent'));
        $this->assertNull($event->getExtension('missing'));
        $this->assertEquals('fallback', $event->getExtension('missing', 'fallback'));
    }

    public function testExtensionRoundTripIsLossless(): void
    {
        $original = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            time: '2025-11-07T10:00:00Z',
            data: ['key' => 'value'],
            extensions: ['traceparent' => '00-abc-def-01', 'retrycount' => 3]
        );

        $array = $original->toArray();

        $this->assertEquals('00-abc-def-01', $array['traceparent']);
        $this->assertEquals(3, $array['retrycount']);

        $restored = CloudEvent::fromArray($array);

        $this->assertEquals($original->getExtensions(), $restored->getExtensions());
        $this->assertEquals($array, $restored->toArray());
    }

    public function testExtensionNamesMustBeLowercaseAlphanumeric(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute name: traceParent');

        new CloudEvent(extensions: ['traceParent' => 'x']);
    }

    public function testExtensionNameMayNotCollideWithSpecAttribute(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute name: type');

        new CloudEvent(extensions: ['type' => 'x']);
    }

    public function testExtensionValueMustBeStringIntegerOrBoolean(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute value for "trace": must be a string, integer or boolean');

        new CloudEvent(extensions: ['trace' => ['nested' => 'value']]);
    }

    public function testFloatExtensionValueIsRejected(): void
    {
        // The CloudEvents type system has no floating-point type
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute value for "sampling": must be a string, integer or boolean');

        new CloudEvent(extensions: ['sampling' => 0.5]);
    }

    public function testFloatExtensionValueIsDroppedWhenLenient(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'sampling' => 0.5,
            'traceparent' => '00-abc-def-01',
        ], lenient: true);

        $this->assertEquals(['traceparent' => '00-abc-def-01'], $event->getExtensions());
    }

    public function testNumericExtensionNameRoundTripsLosslessly(): void
    {
        // PHP casts a digits-only key to an int, which array_merge() would renumber
        $original = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            id: 'test-id',
            extensions: ['123' => 'x', 'traceparent' => '00-abc-def-01']
        );

        $array = $original->toArray();

        $this->assertArrayHasKey('123', $array);
        $this->assertEquals('x', $array['123'] ?? null);
        $this->assertArrayNotHasKey(0, $array);
        $this->assertStringContainsString('"123":"x"', (string) json_encode($array));

        $restored = CloudEvent::fromArray($array);

        $this->assertEquals($original->getExtensions(), $restored->getExtensions());
        $this->assertEquals('x', $restored->getExtension('123'));
        $this->assertEquals($array, $restored->toArray());
    }

    public function testNullExtensionValueIsTreatedAsUnset(): void
    {
        $event = new CloudEvent(extensions: ['traceparent' => null]);

        $this->assertEquals([], $event->getExtensions());
    }

    /**
     * Item 5: the OPTIONAL `dataschema` attribute.
     */
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

        $restored = CloudEvent::fromArray($event->toArray());

        $this->assertEquals('https://example.com/schemas/user.json', $restored->dataschema);
        $this->assertEquals([], $restored->getExtensions());
    }

    /**
     * Item 6: withers.
     */
    public function testWithers(): void
    {
        $event = new CloudEvent(type: 'test.event', source: 'test-service');

        $staged = $event
            ->withId('event-123')
            ->withTime('2025-11-07T10:00:00Z')
            ->withSource('/services/test')
            ->withSubject('user-1')
            ->withData(['key' => 'value'])
            ->withExtension('traceparent', '00-abc-def-01');

        $this->assertEquals('event-123', $staged->id);
        $this->assertEquals('2025-11-07T10:00:00Z', $staged->time);
        $this->assertEquals('/services/test', $staged->source);
        $this->assertEquals('user-1', $staged->subject);
        $this->assertEquals(['key' => 'value'], $staged->data);
        $this->assertEquals('00-abc-def-01', $staged->getExtension('traceparent'));
        $this->assertEquals('test.event', $staged->type);

        // The original is untouched
        $this->assertNotSame($event, $staged);
        $this->assertEquals('', $event->id);
        $this->assertEquals('', $event->time);
        $this->assertEquals('test-service', $event->source);
        $this->assertNull($event->subject);
        $this->assertEquals([], $event->data);
        $this->assertEquals([], $event->getExtensions());
    }

    public function testWithersAcceptNullValues(): void
    {
        $event = new CloudEvent(
            type: 'test.event',
            source: 'test-service',
            subject: 'user-1',
            id: 'test-id',
            data: ['key' => 'value']
        );

        $this->assertNull($event->withSubject(null)->subject);
        $this->assertNull($event->withData(null)->data);
    }

    public function testWithTimeStampsTheCurrentTimeByDefault(): void
    {
        $event = (new CloudEvent(type: 'test.event', source: 'test-service'))->withTime();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $event->time);
    }

    public function testWithExtensionRejectsInvalidName(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute name: trace_parent');

        (new CloudEvent())->withExtension('trace_parent', 'x');
    }

    public function testWithExtensionPreservesNumericName(): void
    {
        $event = (new CloudEvent(type: 'test.event', source: 'test-service', id: 'test-id'))
            ->withExtension('123', 'x')
            ->withExtension('traceparent', '00-abc-def-01');

        $this->assertEquals(['123' => 'x', 'traceparent' => '00-abc-def-01'], $event->getExtensions());
        $this->assertEquals('x', $event->getExtension('123'));

        $array = $event->toArray();

        $this->assertArrayNotHasKey(0, $array);
        $this->assertStringContainsString('"123":"x"', (string) json_encode($array));
        $this->assertEquals($event->getExtensions(), CloudEvent::fromArray($array)->getExtensions());
    }

    public function testWithExtensionOverwritesExistingValue(): void
    {
        $event = (new CloudEvent(extensions: ['retrycount' => 1]))->withExtension('retrycount', 2);

        $this->assertEquals(['retrycount' => 2], $event->getExtensions());
    }

    public function testWithExtensionKeepsExistingExtensions(): void
    {
        $event = (new CloudEvent(extensions: ['traceparent' => '00-abc-def-01']))
            ->withExtension('retrycount', 2);

        $this->assertEquals([
            'traceparent' => '00-abc-def-01',
            'retrycount' => 2,
        ], $event->getExtensions());
    }

    /**
     * Item 7: RFC 3339 timestamp helper.
     */
    public function testNow(): void
    {
        $now = CloudEvent::now();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $now);
        $this->assertInstanceOf(\DateTimeImmutable::class, \DateTimeImmutable::createFromFormat(CloudEvent::TIME_FORMAT, $now));

        $parsed = new \DateTimeImmutable($now);

        $this->assertEquals(0, $parsed->getOffset());
        $this->assertEqualsWithDelta(time(), $parsed->getTimestamp(), 5);
    }

    /**
     * Item 8: strict versus lenient decoding.
     */
    public function testStrictDecodeRejectsMalformedOptionalAttribute(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Attribute "subject" must be a string');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'subject' => ['not', 'a', 'string'],
        ]);
    }

    public function testLenientDecodeCoercesMalformedOptionalAttribute(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'subject' => ['not', 'a', 'string'],
            'time' => 12345,
            'data' => ['key' => 'value'],
        ], lenient: true);

        $this->assertNull($event->subject);
        $this->assertEquals('', $event->time);
        $this->assertEquals('test-id', $event->id);
        $this->assertEquals(['key' => 'value'], $event->data);
        $this->assertTrue($event->validate());
    }

    public function testLenientDecodeDropsInvalidExtensions(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'traceparent' => '00-abc-def-01',
            'traceParent' => 'invalid name',
            'nested' => ['not' => 'scalar'],
        ], lenient: true);

        $this->assertEquals(['traceparent' => '00-abc-def-01'], $event->getExtensions());
    }

    public function testStrictDecodeRejectsInvalidExtensions(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Invalid extension attribute name: traceParent');

        CloudEvent::fromArray([
            'specversion' => '1.0',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
            'traceParent' => 'invalid name',
        ]);
    }

    public function testLenientDecodeStillRejectsUnknownSpecversionByDefault(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Unsupported CloudEvents spec version: 1.1');

        CloudEvent::fromArray([
            'specversion' => '1.1',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
        ], lenient: true);
    }

    public function testLenientDecodeCanAcceptUnknownSpecversion(): void
    {
        $event = CloudEvent::fromArray([
            'specversion' => '1.1',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
        ], lenient: true, allowUnknownSpecversion: true);

        $this->assertEquals('1.1', $event->specversion);

        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Unsupported CloudEvents spec version: 1.1');

        $event->validate();
    }

    public function testStrictDecodeRejectsUnknownSpecversionEvenWhenAllowed(): void
    {
        $this->expectException(CloudEventException::class);
        $this->expectExceptionMessage('Unsupported CloudEvents spec version: 1.1');

        CloudEvent::fromArray([
            'specversion' => '1.1',
            'type' => 'test.event',
            'source' => 'test-service',
            'id' => 'test-id',
        ], allowUnknownSpecversion: true);
    }

    public function testLenientDecodeStillRequiresSpecversionAndType(): void
    {
        try {
            CloudEvent::fromArray(['type' => 'test.event'], lenient: true);
            $this->fail('Expected a CloudEvents exception');
        } catch (CloudEventException $e) {
            $this->assertEquals('Missing required field: specversion', $e->getMessage());
        }

        try {
            CloudEvent::fromArray(['specversion' => '1.0'], lenient: true);
            $this->fail('Expected a CloudEvents exception');
        } catch (CloudEventException $e) {
            $this->assertEquals('Missing required field: type', $e->getMessage());
        }
    }
}
