<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use InvalidArgumentException;

final class Artifact
{
    public const MODES = ['copy', 'stub', 'managed-block', 'reference'];

    public function __construct(
        public readonly string $id,
        public readonly string $from,
        public readonly ?string $to,
        public readonly string $mode,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'from', 'mode'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
                throw new InvalidArgumentException(sprintf('El artefacto necesita un "%s" no vacío.', $key));
            }
        }

        $mode = $data['mode'];

        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Modo desconocido "%s" en el artefacto %s. Válidos: %s.',
                $mode,
                $data['id'],
                implode(', ', self::MODES),
            ));
        }

        $to = $data['to'] ?? null;

        if ($mode !== 'reference' && (! is_string($to) || $to === '')) {
            throw new InvalidArgumentException(sprintf('El artefacto %s necesita un "to" no vacío.', $data['id']));
        }

        return new self(
            id: $data['id'],
            from: $data['from'],
            to: is_string($to) ? $to : null,
            mode: $mode,
        );
    }
}
