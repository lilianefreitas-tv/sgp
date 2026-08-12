<?php

namespace App\Services;

use LogicException;

class ArtifactSnapshotCanonicalizer
{
    /** @param array<mixed> $value @return array<mixed> */
    public function canonicalize(array $value): array
    {
        return $this->normalize($value, 0);
    }

    /** @param array<mixed> $value @return array<mixed> */
    private function normalize(array $value, int $depth): array
    {
        if ($depth > 32) {
            throw new LogicException('O conteúdo estruturado excede a profundidade máxima permitida.');
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->normalizeValue($item, $depth + 1), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new LogicException('Objetos JSON estruturados devem usar chaves textuais.');
            }
            $normalized[$key] = $this->normalizeValue($item, $depth + 1);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private function normalizeValue(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $this->normalize($value, $depth);
        }
        if (is_null($value) || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        throw new LogicException('O conteúdo estruturado contém um valor não serializável.');
    }

    /** @param array<mixed> $envelope */
    public function checksum(array $envelope): string
    {
        return hash('sha256', json_encode($this->canonicalize($envelope), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
