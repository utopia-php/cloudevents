<?php

namespace Utopia\Event;

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
     * @param string $specversion CloudEvents spec version (default: "1.0")
     * @param string $type Event type that maps to worker (e.g., "v1-stats-usage")
     * @param string $source Event source (e.g., "imagine")
     * @param string|null $subject Optional subject, typically project ID
     * @param string $id Unique event identifier
     * @param string $time Event timestamp in RFC3339 format
     * @param string $datacontenttype Content type of data (default: "application/json")
     * @param array<string, mixed> $data Event data payload
     */
    public function __construct(
        public readonly string $specversion = '1.0',
        public readonly string $type = '',
        public readonly string $source = '',
        public readonly ?string $subject = null,
        public readonly string $id = '',
        public readonly string $time = '',
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
        if (!isset($array['specversion'])) {
            throw new InvalidArgumentException('Missing required field: specversion');
        }

        if ($array['specversion'] !== '1.0') {
            throw new InvalidArgumentException('Unsupported CloudEvents spec version: ' . $array['specversion']);
        }

        if (!isset($array['type']) || empty($array['type'])) {
            throw new InvalidArgumentException('Missing required field: type');
        }

        return new self(
            specversion: $array['specversion'],
            type: $array['type'],
            source: $array['source'],
            subject: $array['subject'] ?? null,
            id: $array['id'],
            time: $array['time'],
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

        if (empty($this->type)) {
            throw new InvalidArgumentException('Event type is required');
        }

        return true;
    }
}
