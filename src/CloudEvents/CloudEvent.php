<?php

namespace Utopia\CloudEvents;

use InvalidArgumentException;
use JsonException;

/**
 * CloudEvent class representing the CloudEvents v1.0 specification
 * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/spec.md
 */
class CloudEvent
{
    /**
     * Names reserved for core context attributes, which extension
     * attributes must not use.
     */
    private const RESERVED_ATTRIBUTES = [
        'specversion',
        'type',
        'source',
        'id',
        'subject',
        'time',
        'datacontenttype',
        'dataschema',
        'data',
    ];

    /**
     * CloudEvent constructor
     *
     * @param string $type Event type describing the occurrence (e.g., "com.example.user.created")
     * @param string $source URI-reference identifying the context in which the event happened
     * @param string $id Event identifier, unique within the scope of the source
     * @param string $specversion CloudEvents spec version (default: "1.0")
     * @param string|null $subject Optional subject of the event in the context of the source
     * @param string|null $time Optional event timestamp in RFC 3339 format
     * @param string|null $datacontenttype Optional content type of data (RFC 2046, e.g., "application/json")
     * @param mixed $data Optional event payload of any type
     * @param string|null $dataschema Optional URI identifying the schema that data adheres to
     * @param array<string, mixed> $extensions Extension attributes (lowercase alphanumeric names, boolean/integer/string values)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $source,
        public readonly string $id,
        public readonly string $specversion = '1.0',
        public readonly ?string $subject = null,
        public readonly ?string $time = null,
        public readonly ?string $datacontenttype = null,
        public readonly mixed $data = null,
        public readonly ?string $dataschema = null,
        public readonly array $extensions = []
    ) {
    }

    /**
     * Return a copy of the event with the given extension attribute set
     *
     * @param string $name
     * @param bool|int|string $value
     * @return self
     * @throws InvalidArgumentException
     */
    public function withExtension(string $name, bool|int|string $value): self
    {
        self::assertValidExtensionName($name);

        return new self(
            type: $this->type,
            source: $this->source,
            id: $this->id,
            specversion: $this->specversion,
            subject: $this->subject,
            time: $this->time,
            datacontenttype: $this->datacontenttype,
            dataschema: $this->dataschema,
            data: $this->data,
            extensions: \array_merge($this->extensions, [$name => $value])
        );
    }

    /**
     * Get an extension attribute value, or null when not set
     *
     * @param string $name
     * @return mixed
     */
    public function getExtension(string $name): mixed
    {
        return $this->extensions[$name] ?? null;
    }

    /**
     * Create CloudEvent from array
     *
     * @param array<string, mixed> $array
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $array): self
    {
        foreach (['specversion', 'type', 'source', 'id'] as $field) {
            if (!isset($array[$field]) || !\is_string($array[$field]) || $array[$field] === '') {
                throw new InvalidArgumentException('Missing required field: ' . $field);
            }
        }

        if ($array['specversion'] !== '1.0') {
            throw new InvalidArgumentException('Unsupported CloudEvents spec version: ' . $array['specversion']);
        }

        $extensions = \array_diff_key($array, \array_flip(self::RESERVED_ATTRIBUTES));

        foreach ($extensions as $name => $value) {
            if ($value === null) {
                unset($extensions[$name]);
                continue;
            }

            self::assertValidExtensionName((string) $name);

            if (!\is_bool($value) && !\is_int($value) && !\is_string($value)) {
                throw new InvalidArgumentException('Extension attribute "' . $name . '" must be a boolean, integer or string');
            }
        }

        return new self(
            type: $array['type'],
            source: $array['source'],
            id: $array['id'],
            specversion: $array['specversion'],
            subject: $array['subject'] ?? null,
            time: $array['time'] ?? null,
            datacontenttype: $array['datacontenttype'] ?? null,
            data: $array['data'] ?? null,
            dataschema: $array['dataschema'] ?? null,
            extensions: $extensions
        );
    }

    /**
     * Convert CloudEvent to array
     *
     * Optional attributes that are absent are omitted, since the spec
     * does not allow null attribute values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'specversion' => $this->specversion,
            'type' => $this->type,
            'source' => $this->source,
            'id' => $this->id,
        ];

        if ($this->subject !== null) {
            $array['subject'] = $this->subject;
        }

        if ($this->time !== null) {
            $array['time'] = $this->time;
        }

        if ($this->datacontenttype !== null) {
            $array['datacontenttype'] = $this->datacontenttype;
        }

        if ($this->dataschema !== null) {
            $array['dataschema'] = $this->dataschema;
        }

        if ($this->data !== null) {
            $array['data'] = $this->data;
        }

        return $array + $this->extensions;
    }

    /**
     * Create CloudEvent from its JSON event format representation
     *
     * Binary payloads carried in the data_base64 member are decoded
     * into data.
     *
     * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/formats/json-format.md
     *
     * @param string $json
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json): self
    {
        try {
            $decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid CloudEvent JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('CloudEvent JSON must decode to an object');
        }

        if (\array_key_exists('data_base64', $decoded)) {
            if (\array_key_exists('data', $decoded)) {
                throw new InvalidArgumentException('CloudEvent must not contain both data and data_base64');
            }

            if (!\is_string($decoded['data_base64'])) {
                throw new InvalidArgumentException('data_base64 must be a string');
            }

            $binary = \base64_decode($decoded['data_base64'], true);

            if ($binary === false) {
                throw new InvalidArgumentException('data_base64 must be valid Base64');
            }

            unset($decoded['data_base64']);
            $decoded['data'] = $binary;
        }

        return self::fromArray($decoded);
    }

    /**
     * Serialize the CloudEvent to the JSON event format
     *
     * String data that is not valid UTF-8 (and therefore cannot be
     * carried in the data member) is emitted as the data_base64 member.
     *
     * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/formats/json-format.md
     *
     * @param int $flags json_encode() flags
     * @return string
     * @throws InvalidArgumentException
     */
    public function toJson(int $flags = 0): string
    {
        $array = $this->toArray();

        if (\is_string($this->data) && \preg_match('//u', $this->data) !== 1) {
            unset($array['data']);
            $array['data_base64'] = \base64_encode($this->data);
        }

        try {
            return \json_encode($array, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode CloudEvent as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate the CloudEvent
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validate(): bool
    {
        if ($this->specversion !== '1.0') {
            throw new InvalidArgumentException('Unsupported CloudEvents spec version: ' . $this->specversion);
        }

        if ($this->type === '') {
            throw new InvalidArgumentException('Event type is required');
        }

        if ($this->source === '') {
            throw new InvalidArgumentException('Event source is required');
        }

        if ($this->id === '') {
            throw new InvalidArgumentException('Event id is required');
        }

        if ($this->subject === '') {
            throw new InvalidArgumentException('Event subject must not be empty when present');
        }

        if ($this->time === '') {
            throw new InvalidArgumentException('Event time must not be empty when present');
        }

        if ($this->datacontenttype !== null && \trim($this->datacontenttype) === '') {
            throw new InvalidArgumentException('Event datacontenttype must not be empty when present');
        }

        if ($this->dataschema === '') {
            throw new InvalidArgumentException('Event dataschema must not be empty when present');
        }

        foreach ($this->extensions as $name => $value) {
            self::assertValidExtensionName((string) $name);

            if (!\is_bool($value) && !\is_int($value) && !\is_string($value)) {
                throw new InvalidArgumentException('Extension attribute "' . $name . '" must be a boolean, integer or string');
            }
        }

        return true;
    }

    /**
     * Assert that a name is a valid, non-reserved extension attribute name
     *
     * @param string $name
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertValidExtensionName(string $name): void
    {
        if (!\preg_match('/^[a-z0-9]+$/', $name)) {
            throw new InvalidArgumentException('Extension attribute name must contain only lowercase letters and digits: ' . $name);
        }

        if (\in_array($name, self::RESERVED_ATTRIBUTES, true)) {
            throw new InvalidArgumentException('Extension attribute name conflicts with a core attribute: ' . $name);
        }
    }
}
