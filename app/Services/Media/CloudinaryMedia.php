<?php

namespace App\Services\Media;

class CloudinaryMedia
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $resourceType,
        public readonly ?string $format,
        public readonly string $secureUrl,
        public readonly ?string $assetId,
        public readonly int $bytes,
        public readonly ?string $originalFilename,
    ) {}

    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'resource_type' => $this->resourceType,
            'format' => $this->format,
            'secure_url' => $this->secureUrl,
            'asset_id' => $this->assetId,
            'bytes' => $this->bytes,
            'original_filename' => $this->originalFilename,
        ];
    }
}
