<?php

namespace Pterodactyl\Support;

class Cpf
{
    public static function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isValid(string $value): bool
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
            }

            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
