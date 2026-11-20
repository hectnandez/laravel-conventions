<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use RuntimeException;

final class ManagedBlock
{
    public const START = '<!-- conventions:start -->';

    public const END = '<!-- conventions:end -->';

    public static function extract(string $contents): ?string
    {
        $start = strpos($contents, self::START);

        if ($start === false) {
            return null;
        }

        $end = strpos($contents, self::END, $start + strlen(self::START));

        if ($end === false) {
            return null;
        }

        return trim(substr($contents, $start + strlen(self::START), $end - $start - strlen(self::START)));
    }

    public static function wrap(string $inner): string
    {
        return self::START."\n".trim($inner)."\n".self::END;
    }

    public static function content(string $contents): string
    {
        return self::extract($contents) ?? trim($contents);
    }

    public static function apply(string $contents, string $inner): string
    {
        $block = self::wrap($inner);
        $start = strpos($contents, self::START);

        if ($start === false) {
            $prefix = rtrim($contents);

            return ($prefix === '' ? '' : $prefix."\n\n").$block."\n";
        }

        $end = strpos($contents, self::END, $start);

        if ($end === false) {
            throw new RuntimeException(sprintf(
                'El archivo destino tiene %s pero no %s. Corrígelo a mano antes de sincronizar.',
                self::START,
                self::END,
            ));
        }

        $end += strlen(self::END);
        $result = substr($contents, 0, $start).$block.substr($contents, $end);

        return str_ends_with($result, "\n") ? $result : $result."\n";
    }
}
