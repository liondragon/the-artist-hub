<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Price formula parser and resolver.
 */
final class TAH_Price_Formula
{
    const MODE_DEFAULT = 'default';
    const MODE_ADDITION = 'addition';
    const MODE_PERCENTAGE = 'percentage';
    const MODE_OVERRIDE = 'override';

    const ROUNDING_NEAREST = 'nearest';
    const ROUNDING_UP = 'up';
    const ROUNDING_DOWN = 'down';

    /**
     * Parse a formula string into storage fields.
     *
     * @return array{mode:string,modifier:float}
     */
    public static function parse($input)
    {
        $raw_input = is_string($input) ? trim($input) : '';

        if ($raw_input === '' || $raw_input === '$') {
            return [
                'mode' => self::MODE_DEFAULT,
                'modifier' => 0.0,
            ];
        }

        if (preg_match('/^\$\s*([+-])\s*(\d+(?:\.\d+)?)$/', $raw_input, $matches) === 1) {
            $modifier = (float) $matches[2];
            if ($matches[1] === '-') {
                $modifier *= -1;
            }

            return [
                'mode' => self::MODE_ADDITION,
                'modifier' => $modifier,
            ];
        }

        if (preg_match('/^\$\s*\*\s*([+-]?\d+(?:\.\d+)?)$/', $raw_input, $matches) === 1) {
            return [
                'mode' => self::MODE_PERCENTAGE,
                'modifier' => (float) $matches[1],
            ];
        }

        if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw_input) === 1) {
            return [
                'mode' => self::MODE_OVERRIDE,
                'modifier' => (float) $raw_input,
            ];
        }

        return [
            'mode' => self::MODE_DEFAULT,
            'modifier' => 0.0,
        ];
    }

    /**
     * Resolve effective unit price for a line item.
     */
    public static function resolve($mode, $modifier, $catalog_price, $rounding = 1, $direction = self::ROUNDING_NEAREST)
    {
        $mode = is_string($mode) ? $mode : self::MODE_DEFAULT;
        $modifier = (float) $modifier;
        $catalog_price = (float) $catalog_price;

        switch ($mode) {
            case self::MODE_ADDITION:
                $resolved_price = $catalog_price + $modifier;
                break;

            case self::MODE_PERCENTAGE:
                $resolved_price = $catalog_price * $modifier;
                break;

            case self::MODE_OVERRIDE:
                $resolved_price = $modifier;
                break;

            case self::MODE_DEFAULT:
            default:
                $resolved_price = $catalog_price;
                break;
        }

        return self::apply_mround($resolved_price, (float) $rounding, is_string($direction) ? $direction : self::ROUNDING_NEAREST);
    }

    /**
     * Apply MROUND-style rounding to resolved prices.
     */
    private static function apply_mround(float $value, float $multiple, string $direction)
    {
        if ($multiple <= 0.000001) {
            return round($value, 2);
        }

        $direction = in_array($direction, [self::ROUNDING_NEAREST, self::ROUNDING_UP, self::ROUNDING_DOWN], true)
            ? $direction
            : self::ROUNDING_NEAREST;

        $scaled = $value / $multiple;

        if ($direction === self::ROUNDING_UP) {
            return round(ceil($scaled) * $multiple, 2);
        }

        if ($direction === self::ROUNDING_DOWN) {
            return round(floor($scaled) * $multiple, 2);
        }

        return round(round($scaled) * $multiple, 2);
    }
}
