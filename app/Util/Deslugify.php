<?php

namespace App\Util;
/**
 * Class Deslugify
 * 
 * This class is responsible for converting slugs into human-readable terms.
 * It is particularly useful for converting automotive repair terms from a slug format to a more user-friendly format.
 */

class Deslugify
{
    /**
     * Convert a slug into a human-readable repair term.
     * 
     * @param string $slug The slug to convert
     * @return string The human-readable term
     */
    public static function deslugify(string $slug): string
    {
        // Replace hyphens with spaces
        $term = str_replace('-', ' ', $slug);

        // Apply special case transformations for common automotive terms
        $specialCases = [
            'abs' => 'ABS',
            'ac' => 'AC',
            'hvac' => 'HVAC',
            'cv' => 'CV',
            'ecm' => 'ECM',
            'ecu' => 'ECU',
            'pcm' => 'PCM',
            'tpms' => 'TPMS',
            'dpf' => 'DPF',
            'egr' => 'EGR',
            'maf' => 'MAF',
            'tps' => 'TPS',
            'vvt' => 'VVT',
        ];

        $words = explode(' ', $term);
        $result = [];

        foreach ($words as $word) {
            if (isset($specialCases[$word])) {
                $result[] = $specialCases[$word];
            } else {
                // Capitalize first letter of each word
                $result[] = ucfirst($word);
            }
        }

        return implode(' ', $result);
    }
}