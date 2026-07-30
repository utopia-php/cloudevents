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
     * @param string $datacontenttype Content type of data (default: "application/json")
     * @param array<string, mixed> $data Event data payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $source,
        public readonly string $id,
        public readonly string $specversion = '1.0',
        public readonly ?string $subject = null,
        public readonly ?string $time = null,
        public readonly string $datacontenttype = 'application/json',
        public readonly array $data = []
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
            datacontenttype: $array['datacontenttype'] ?? 'application/json',
            data: $array['data'] ?? []
        );
    }

    /**
     * Convert CloudEvent to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'specversion' => $this->specversion,
            'type' => $this->type,
            'source' => $this->source,
            'subject' => $this->subject,
            'id' => $this->id,
            'time' => $this->time,
            'datacontenttype' => $this->datacontenttype,
            'data' => $this->data
        ];
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

        return true;
    }
}
