<?php

namespace Utopia\CloudEvents;

use DateTimeImmutable;
use DateTimeZone;

/**
 * CloudEvent class representing the CloudEvents v1.0 specification
 *
 * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/spec.md
 * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/formats/json-format.md
 */
class CloudEvent
{
    /**
     * The only spec version this library implements.
     */
    public const SPECVERSION = '1.0';

    /**
     * RFC 3339 (UTC, millisecond precision) format string.
     *
     * PHP's DATE_ATOM renders UTC as "+00:00" and carries no sub-second part,
     * so it is not used here.
     */
    public const TIME_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * Attribute names owned by the spec, which therefore may not be used as
     * extension attribute names.
     *
     * @var array<int, string>
     */
    private const RESERVED = [
        'specversion',
        'type',
        'source',
        'subject',
        'id',
        'time',
        'datacontenttype',
        'dataschema',
        'data',
        'data_base64',
    ];

    /**
     * Extension context attributes, keyed by attribute name.
     *
     * A digits-only name such as "123" is legal, and PHP stores it as an int key,
     * which is why the key type here is array-key rather than string.
     *
     * @var array<array-key, string|int|bool>
     */
    public readonly array $extensions;

    /**
     * CloudEvent constructor
     *
     * @param  string  $specversion  CloudEvents spec version (default: "1.0")
     * @param  string  $type  Event type that maps to worker (e.g., "v1-stats-usage")
     * @param  string  $source  Event source, a non-empty URI-reference (e.g., "imagine")
     * @param  string|null  $subject  Optional subject, typically project ID
     * @param  string  $id  Unique event identifier
     * @param  string  $time  Event timestamp in RFC 3339 format, see self::now()
     * @param  string  $datacontenttype  Content type of data (default: "application/json")
     * @param  mixed  $data  Event data payload. The JSON format leaves this unrestricted,
     *                       so an array, string, number, boolean or null are all valid.
     * @param  string|null  $dataschema  Optional URI identifying the schema of $data
     * @param  array<array-key, mixed>  $extensions  Extension context attributes. Names must
     *                                               consist of lowercase a-z and 0-9 only and
     *                                               must not collide with a spec attribute.
     *
     * @throws Exception on an invalid extension attribute name or value
     */
    public function __construct(
        public readonly string $specversion = self::SPECVERSION,
        public readonly string $type = '',
        public readonly string $source = '',
        public readonly ?string $subject = null,
        public readonly string $id = '',
        public readonly string $time = '',
        public readonly string $datacontenttype = 'application/json',
        public readonly mixed $data = [],
        public readonly ?string $dataschema = null,
        array $extensions = [],
    ) {
        $this->extensions = self::filterExtensions($extensions, lenient: false);
    }

    /**
     * Current time as an RFC 3339 UTC timestamp with milliseconds
     *
     * Produces e.g. "2025-11-07T10:00:00.123Z", which is what the `time`
     * attribute expects.
     */
    public static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::TIME_FORMAT);
    }

    /**
     * Create CloudEvent from array
     *
     * Per the JSON format, an attribute whose value is null is treated as unset,
     * and any member that is not a spec attribute is carried as an extension.
     *
     * Strict mode (the default) raises an Exception when:
     *  - `specversion` is missing, or is not the string "1.0";
     *  - `type` is missing, empty, or not a string;
     *  - any other spec attribute is present with a non-string value;
     *  - an extension attribute has an invalid name, or a value that is not a
     *    string, integer or boolean.
     *
     * Lenient mode ($lenient = true) raises an Exception only for the `specversion`
     * and `type` failures above; it never invents a required attribute. Every other
     * malformed attribute is coerced to its default (so a non-string `subject`
     * becomes null) and every invalid extension attribute is dropped, so a single
     * bad optional attribute from an uncontrolled producer still yields a usable
     * event. Pass $allowUnknownSpecversion to also survive a producer that has
     * moved to a spec version this library does not know; the unknown version is
     * kept verbatim on the returned event, where validate() will still reject it.
     *
     * Neither mode enforces the presence of `id` and `source`; call validate() for
     * a full conformance check.
     *
     * @param  array<array-key, mixed>  $array
     * @param  bool  $lenient  Coerce malformed optional attributes instead of throwing
     * @param  bool  $allowUnknownSpecversion  Accept an unknown spec version (lenient mode only)
     *
     * @throws Exception
     */
    public static function fromArray(array $array, bool $lenient = false, bool $allowUnknownSpecversion = false): self
    {
        if (!isset($array['specversion'])) {
            throw new Exception('Missing required field: specversion');
        }

        if (!is_string($array['specversion'])) {
            throw new Exception('Attribute "specversion" must be a string');
        }

        $specversion = $array['specversion'];

        if ($specversion !== self::SPECVERSION && !($lenient && $allowUnknownSpecversion)) {
            throw new Exception('Unsupported CloudEvents spec version: '.$specversion);
        }

        if (!isset($array['type'])) {
            throw new Exception('Missing required field: type');
        }

        if (!is_string($array['type'])) {
            throw new Exception('Attribute "type" must be a string');
        }

        if ($array['type'] === '') {
            throw new Exception('Missing required field: type');
        }

        $extensions = [];

        foreach ($array as $name => $value) {
            if (in_array((string) $name, self::RESERVED, true)) {
                continue;
            }

            $extensions[$name] = $value;
        }

        return new self(
            specversion: $specversion,
            type: $array['type'],
            source: self::readString($array, 'source', $lenient) ?? '',
            subject: self::readString($array, 'subject', $lenient),
            id: self::readString($array, 'id', $lenient) ?? '',
            time: self::readString($array, 'time', $lenient) ?? '',
            datacontenttype: self::readString($array, 'datacontenttype', $lenient) ?? 'application/json',
            // Absent data defaults to an empty array, but an explicit null is a
            // valid payload and is kept as-is so a round trip stays lossless.
            data: array_key_exists('data', $array) ? $array['data'] : [],
            dataschema: self::readString($array, 'dataschema', $lenient),
            extensions: self::filterExtensions($extensions, $lenient),
        );
    }

    /**
     * Convert CloudEvent to array
     *
     * Every spec attribute is always present; a null value means unset, which the
     * JSON format treats as equivalent to omitting the member. Extension attributes
     * are emitted as top-level members alongside them.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        // The union operator rather than array_merge(), which would renumber a
        // digits-only extension name such as "123" that PHP has cast to an int key.
        return [
            'specversion' => $this->specversion,
            'type' => $this->type,
            'source' => $this->source,
            'subject' => $this->subject,
            'id' => $this->id,
            'time' => $this->time,
            'datacontenttype' => $this->datacontenttype,
            'dataschema' => $this->dataschema,
            'data' => $this->data,
        ] + $this->extensions;
    }

    /**
     * Validate the CloudEvent
     *
     * Enforces the four REQUIRED context attributes: `id`, `source`, `specversion`
     * and `type`, where `id` must be a non-empty string and `source` a non-empty
     * URI-reference.
     *
     * @throws Exception
     */
    public function validate(): bool
    {
        if ($this->specversion !== self::SPECVERSION) {
            throw new Exception('Unsupported CloudEvents spec version: '.$this->specversion);
        }

        if ($this->type === '') {
            throw new Exception('Event type is required');
        }

        if ($this->id === '') {
            throw new Exception('Event id is required');
        }

        if ($this->source === '') {
            throw new Exception('Event source is required');
        }

        if (!self::isUriReference($this->source)) {
            throw new Exception('Event source must be a valid URI-reference');
        }

        return true;
    }

    /**
     * Get a single extension attribute
     *
     * @return string|int|bool|null The $default when the attribute is not set
     */
    public function getExtension(string $name, string|int|bool|null $default = null): string|int|bool|null
    {
        return $this->extensions[$name] ?? $default;
    }

    /**
     * Get all extension attributes, keyed by attribute name
     *
     * @return array<array-key, string|int|bool>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Return a copy with the given id
     */
    public function withId(string $id): self
    {
        return $this->with(id: $id);
    }

    /**
     * Return a copy with the given time
     *
     * @param  string|null  $time  RFC 3339 timestamp, or null to stamp the current time
     */
    public function withTime(?string $time = null): self
    {
        return $this->with(time: $time ?? self::now());
    }

    /**
     * Return a copy with the given source
     */
    public function withSource(string $source): self
    {
        return $this->with(source: $source);
    }

    /**
     * Return a copy with the given subject
     */
    public function withSubject(?string $subject): self
    {
        return $this->with(subject: $subject, subjectSet: true);
    }

    /**
     * Return a copy with the given data payload
     */
    public function withData(mixed $data): self
    {
        return $this->with(data: $data, dataSet: true);
    }

    /**
     * Return a copy with the given extension attribute set
     *
     * @param  string  $name  Lowercase a-z and 0-9 only, and not a spec attribute name
     * @param  string|int|bool|null  $value  A null value unsets the attribute
     *
     * @throws Exception on an invalid extension attribute name or value
     */
    public function withExtension(string $name, mixed $value): self
    {
        return $this->with(extensions: array_merge($this->extensions, [$name => $value]));
    }

    /**
     * Build a copy of this event, overriding the given attributes
     *
     * @param  array<array-key, mixed>|null  $extensions
     * @param  bool  $subjectSet  Whether $subject was given, since null is a meaningful value
     * @param  bool  $dataSet  Whether $data was given, since null is a meaningful value
     *
     * @throws Exception
     */
    private function with(
        ?string $type = null,
        ?string $source = null,
        ?string $subject = null,
        ?string $id = null,
        ?string $time = null,
        ?string $datacontenttype = null,
        mixed $data = null,
        ?array $extensions = null,
        bool $subjectSet = false,
        bool $dataSet = false,
    ): self {
        return new self(
            specversion: $this->specversion,
            type: $type ?? $this->type,
            source: $source ?? $this->source,
            subject: $subjectSet ? $subject : $this->subject,
            id: $id ?? $this->id,
            time: $time ?? $this->time,
            datacontenttype: $datacontenttype ?? $this->datacontenttype,
            data: $dataSet ? $data : $this->data,
            dataschema: $this->dataschema,
            extensions: $extensions ?? $this->extensions,
        );
    }

    /**
     * Read a string attribute, treating an explicit null as unset
     *
     * @param  array<array-key, mixed>  $array
     * @return string|null Null when the attribute is unset, or when it is malformed
     *                     and $lenient is true
     *
     * @throws Exception when the attribute is malformed and $lenient is false
     */
    private static function readString(array $array, string $name, bool $lenient): ?string
    {
        if (!isset($array[$name])) {
            return null;
        }

        if (!is_string($array[$name])) {
            if ($lenient) {
                return null;
            }

            throw new Exception('Attribute "'.$name.'" must be a string');
        }

        return $array[$name];
    }

    /**
     * Validate extension attribute names and values
     *
     * Names are restricted to lowercase a-z and 0-9 by the spec. Values must be of a
     * CloudEvents type; the type system has no floating-point type, and Binary, URI,
     * URI-reference and Timestamp all serialize as strings, so what remains in JSON
     * is a string, an integer or a boolean. An attribute whose value is null is
     * treated as unset.
     *
     * @param  array<array-key, mixed>  $extensions
     * @return array<array-key, string|int|bool>
     *
     * @throws Exception when an attribute is invalid and $lenient is false
     */
    private static function filterExtensions(array $extensions, bool $lenient): array
    {
        $filtered = [];

        foreach ($extensions as $name => $value) {
            $name = (string) $name;

            if ($value === null) {
                continue;
            }

            if (!preg_match('/^[a-z0-9]+$/', $name) || in_array($name, self::RESERVED, true)) {
                if ($lenient) {
                    continue;
                }

                throw new Exception('Invalid extension attribute name: '.$name);
            }

            if (!is_string($value) && !is_int($value) && !is_bool($value)) {
                if ($lenient) {
                    continue;
                }

                throw new Exception('Invalid extension attribute value for "'.$name.'": must be a string, integer or boolean');
            }

            $filtered[$name] = $value;
        }

        return $filtered;
    }

    /**
     * Check whether a string is a syntactically valid RFC 3986 URI-reference
     *
     * A URI-reference is either a URI or a relative reference, so "/services/db" and
     * "user-service" are both fine. What it may not contain is a character outside
     * the unreserved and reserved sets — a space, a control character or a raw
     * non-ASCII byte must be percent-encoded — or a malformed percent-escape.
     */
    private static function isUriReference(string $value): bool
    {
        if (preg_match('/^[A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]*$/', $value) !== 1) {
            return false;
        }

        return preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 0;
    }
}
