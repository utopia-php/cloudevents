<?php

namespace Utopia\CloudEvents;

use InvalidArgumentException;

/**
 * CloudEvent class representing the CloudEvents v1.0 specification
 * @see https://github.com/cloudevents/spec/blob/v1.0.2/cloudevents/spec.md
 */
class CloudEvent
{
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
     */
    public function __construct(
        public readonly string $type,
        public readonly string $source,
        public readonly string $id,
        public readonly string $specversion = '1.0',
        public readonly ?string $subject = null,
        public readonly ?string $time = null,
        public readonly ?string $datacontenttype = null,
        public readonly mixed $data = null
    ) {
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

        return new self(
            type: $array['type'],
            source: $array['source'],
            id: $array['id'],
            specversion: $array['specversion'],
            subject: $array['subject'] ?? null,
            time: $array['time'] ?? null,
            datacontenttype: $array['datacontenttype'] ?? null,
            data: $array['data'] ?? null
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

        if ($this->data !== null) {
            $array['data'] = $this->data;
        }

        return $array;
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

        return true;
    }
}
