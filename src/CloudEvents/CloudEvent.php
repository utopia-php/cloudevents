<?php

namespace Utopia\CloudEvents;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

/**
 * CloudEvent class representing the CloudEvents v1.0 specification
 * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/spec.md
 */
class CloudEvent
{
    /**
     * RFC 3339 (UTC, millisecond precision) format string.
     *
     * PHP's DATE_ATOM renders UTC as "+00:00" and carries no sub-second
     * part, so it is not used here.
     */
    public const TIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';

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
     * @param string|null $datacontenttype Content type of data (RFC 2046, default: "application/json"); pass null to leave it unset
     * @param mixed $data Optional event payload of any type
     * @param string|null $dataschema Optional URI identifying the schema that data adheres to
     * @param array<array-key, mixed> $extensions Extension attributes (lowercase alphanumeric names, boolean/integer/string values)
     * @throws InvalidArgumentException When an extension attribute has an invalid name or value
     */
    public function __construct(
        public readonly string $type,
        public readonly string $source,
        public readonly string $id,
        public readonly string $specversion = '1.0',
        public readonly ?string $subject = null,
        public readonly ?string $time = null,
        public readonly ?string $datacontenttype = 'application/json',
        public readonly mixed $data = null,
        public readonly ?string $dataschema = null,
        public readonly array $extensions = []
    ) {
        foreach ($this->extensions as $name => $value) {
            $error = self::extensionError((string) $name, $value);

            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }
        }
    }

    /**
     * Current time as an RFC 3339 UTC timestamp with milliseconds
     *
     * Produces e.g. "2025-11-07T10:00:00.123Z", which is what the time
     * attribute expects.
     *
     * @return string
     */
    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::TIME_FORMAT);
    }

    /**
     * Create CloudEvent from array
     *
     * Unlike the constructor, datacontenttype is not defaulted here: a
     * parsed event keeps the wire form, so an absent attribute stays
     * absent (per the JSON format, absent datacontenttype already
     * implies a JSON payload).
     *
     * @param array<array-key, mixed> $array
     * @param bool $lenient Coerce malformed optional attributes to their default and drop invalid extension attributes instead of throwing
     * @param bool $allowUnknownSpecversion Accept a spec version other than "1.0", which validate() will still reject (lenient mode only)
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $array, bool $lenient = false, bool $allowUnknownSpecversion = false): self
    {
        $specversion = self::readRequiredString($array, 'specversion');
        $type = self::readRequiredString($array, 'type');
        $source = self::readRequiredString($array, 'source');
        $id = self::readRequiredString($array, 'id');

        if ($specversion !== '1.0' && !($lenient && $allowUnknownSpecversion)) {
            throw new InvalidArgumentException('Unsupported CloudEvents spec version: ' . $specversion);
        }

        $extensions = self::filterExtensions(
            \array_diff_key($array, \array_flip(self::RESERVED_ATTRIBUTES)),
            $lenient
        );

        return new self(
            type: $type,
            source: $source,
            id: $id,
            specversion: $specversion,
            subject: self::readString($array, 'subject', $lenient),
            time: self::readString($array, 'time', $lenient),
            datacontenttype: self::readString($array, 'datacontenttype', $lenient),
            data: $array['data'] ?? null,
            dataschema: self::readString($array, 'dataschema', $lenient),
            extensions: $extensions
        );
    }

    /**
     * Convert CloudEvent to array
     *
     * Optional attributes that are absent are omitted, since the spec
     * does not allow null attribute values.
     *
     * @return array<array-key, mixed>
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
     * into data. JSON objects inside data are decoded as stdClass so
     * that object and array payloads keep their JSON type when
     * re-encoded (e.g., an empty object stays {} instead of []).
     *
     * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/formats/json-format.md
     *
     * @param string $json
     * @param bool $lenient See fromArray()
     * @param bool $allowUnknownSpecversion See fromArray()
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromJson(string $json, bool $lenient = false, bool $allowUnknownSpecversion = false): self
    {
        try {
            $raw = \json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid CloudEvent JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!$raw instanceof \stdClass) {
            throw new InvalidArgumentException('CloudEvent JSON must decode to an object');
        }

        $decoded = \get_object_vars($raw);

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

        return self::fromArray($decoded, $lenient, $allowUnknownSpecversion);
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

        // Extension attributes need no checks here: the constructor
        // validates them, and readonly keeps the invariant intact.

        return true;
    }

    /**
     * Read a REQUIRED string attribute
     *
     * @param array<array-key, mixed> $array
     * @param string $name
     * @return string
     * @throws InvalidArgumentException When the attribute is absent, empty or not a string
     */
    private static function readRequiredString(array $array, string $name): string
    {
        if (!isset($array[$name]) || $array[$name] === '') {
            throw new InvalidArgumentException('Missing required field: ' . $name);
        }

        if (!\is_string($array[$name])) {
            throw new InvalidArgumentException('Attribute "' . $name . '" must be a string');
        }

        return $array[$name];
    }

    /**
     * Read an OPTIONAL string attribute, treating an explicit null as unset
     *
     * @param array<array-key, mixed> $array
     * @param string $name
     * @param bool $lenient
     * @return string|null Null when the attribute is unset, or when it is
     *                     malformed and $lenient is true
     * @throws InvalidArgumentException When the attribute is malformed and $lenient is false
     */
    private static function readString(array $array, string $name, bool $lenient): ?string
    {
        if (!isset($array[$name])) {
            return null;
        }

        if (!\is_string($array[$name])) {
            if ($lenient) {
                return null;
            }

            throw new InvalidArgumentException('Attribute "' . $name . '" must be a string');
        }

        return $array[$name];
    }

    /**
     * Drop unset extension attributes, and in lenient mode invalid ones too
     *
     * Null values mean the attribute is unset. Anything else is passed
     * through in strict mode for the constructor to validate.
     *
     * @param array<array-key, mixed> $extensions
     * @param bool $lenient
     * @return array<array-key, mixed>
     */
    private static function filterExtensions(array $extensions, bool $lenient): array
    {
        $filtered = [];

        foreach ($extensions as $name => $value) {
            if ($value === null) {
                continue;
            }

            if ($lenient && self::extensionError((string) $name, $value) !== null) {
                continue;
            }

            $filtered[$name] = $value;
        }

        return $filtered;
    }

    /**
     * Describe why an extension attribute is invalid
     *
     * @param string $name
     * @param mixed $value
     * @return string|null The error message, or null when the attribute is valid
     */
    private static function extensionError(string $name, mixed $value): ?string
    {
        if (!\preg_match('/^[a-z0-9]+$/', $name)) {
            return 'Extension attribute name must contain only lowercase letters and digits: ' . $name;
        }

        if (\in_array($name, self::RESERVED_ATTRIBUTES, true)) {
            return 'Extension attribute name conflicts with a core attribute: ' . $name;
        }

        if (!\is_bool($value) && !\is_int($value) && !\is_string($value)) {
            return 'Extension attribute "' . $name . '" must be a boolean, integer or string';
        }

        return null;
    }
}
