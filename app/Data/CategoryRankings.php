<?php

namespace App\Data;

class CategoryRankings
{
    /**
     * @var array
     */
    protected static $rankings = [
        // High priority
        'air bags' => 'high',
        'back over prevention' => 'high',
        'brakes' => 'high',
        'buckle' => 'high',
        'chest clip' => 'high',
        'child seat' => 'high',
        'drivetrain' => 'high',
        'electronic stability control' => 'high',
        'electronic stability control esc' => 'high',
        'engine' => 'high',
        'engine and engine cooling' => 'high',
        'firerelated' => 'high',
        'forward collision avoidance' => 'high',
        'fuelpropulsion system' => 'high',
        'harness' => 'high',
        'hybrid propulsion system' => 'high',
        'i suspect the car seat is counterfeit' => 'high',
        'lane departure' => 'high',
        'lower anchor on car seat or vehicle' => 'high',
        'parking brake' => 'high',
        'power train' => 'high',
        'seat belts' => 'high',
        'seatbeltsairbags' => 'high',
        'service brakes' => 'high',
        'steering' => 'high',
        'tether' => 'high',
        'traction control system' => 'high',
        'transmission' => 'high',
        'vehicle speed control' => 'high',

        // Medium priority
        'base' => 'medium',
        'carry handle' => 'medium',
        'clutch' => 'medium',
        'communication' => 'medium',
        'coolingsystem' => 'medium',
        'diesel' => 'medium',
        'electric' => 'medium',
        'electrical' => 'medium',
        'electrical system' => 'medium',
        'equipment adaptivemobility' => 'medium',
        'exhaustsystem' => 'medium',
        'exterior lighting' => 'medium',
        'fuel system' => 'medium',
        'fuelsystem' => 'medium',
        'gasoline' => 'medium',
        'hydraulic' => 'medium',
        'latcheslockslinkages' => 'medium',
        'lights' => 'medium',
        'mechanical' => 'medium',
        'seats' => 'medium',
        'shell' => 'medium',
        'structure' => 'medium',
        'suspension' => 'medium',
        'tires' => 'medium',
        'trailer hitches' => 'medium',
        'visibility' => 'medium',
        'visibilitywiper' => 'medium',
        'wheelshubs' => 'medium',
        'wheels' => 'medium',
        'windowswindshield' => 'medium',

        // Low priority
        'accessories' => 'low',
        'acheater' => 'low',
        'air' => 'low',
        'body' => 'low',
        'bodypaint' => 'low',
        'body_paint' => 'low',
        'equipment' => 'low',
        'insert' => 'low',
        'interior lighting' => 'low',
        'miscellaneous' => 'low',
        'none' => 'low',
        'other' => 'low',
        'otheri am not sure' => 'low',
        'otherunknown' => 'low',
        'padding' => 'low',
        'unknown or other' => 'low',
    ];

    // TODO: Map all of the repairs to actual numeric values, not categories

    protected static $numericRankings = [
        // High priority (safety critical) - 100
        'air bags' => 100,
        'back over prevention' => 100,
        'brakes' => 100,
        'buckle' => 100,
        'chest clip' => 100,
        'child seat' => 100,
        'electronic stability control' => 100,
        'electronic stability control esc' => 100,
        'firerelated' => 100,
        'forward collision avoidance' => 100,
        'harness' => 100,
        'i suspect the car seat is counterfeit' => 100,
        'lane departure' => 100,
        'lower anchor on car seat or vehicle' => 100,
        'parking brake' => 100,
        'seat belts' => 100,
        'seatbeltsairbags' => 100,
        'service brakes' => 100,
        'steering' => 100,
        'tether' => 100,
        'traction control system' => 100,
        'vehicle speed control' => 100,

        // High priority (major systems) - 90
        'drivetrain' => 90,
        'engine' => 90,
        'engine and engine cooling' => 90,
        'fuelpropulsion system' => 90,
        'hybrid propulsion system' => 90,
        'power train' => 90,
        'transmission' => 90,

        // Medium priority (important systems) - 70
        'clutch' => 70,
        'coolingsystem' => 70,
        'electrical system' => 70,
        'fuel system' => 70,
        'fuelsystem' => 70,
        'suspension' => 70,
        'tires' => 70,
        'wheels' => 70,

        // Medium priority (comfort/convenience) - 50
        'base' => 50,
        'carry handle' => 50,
        'communication' => 50,
        'diesel' => 50,
        'electric' => 50,
        'electrical' => 50,
        'equipment adaptivemobility' => 50,
        'exhaustsystem' => 50,
        'exterior lighting' => 50,
        'gasoline' => 50,
        'hydraulic' => 50,
        'latcheslockslinkages' => 50,
        'lights' => 50,
        'mechanical' => 50,
        'seats' => 50,
        'shell' => 50,
        'structure' => 50,
        'trailer hitches' => 50,
        'visibility' => 50,
        'visibilitywiper' => 50,
        'wheelshubs' => 50,
        'windowswindshield' => 50,

        // Low priority (cosmetic/minor) - 20
        'accessories' => 20,
        'acheater' => 20,
        'air' => 20,
        'body' => 20,
        'bodypaint' => 20,
        'body_paint' => 20,
        'equipment' => 20,
        'insert' => 20,
        'interior lighting' => 20,
        'miscellaneous' => 20,
        'none' => 20,
        'other' => 20,
        'otheri am not sure' => 20,
        'otherunknown' => 20,
        'padding' => 20,
        'unknown or other' => 20,
    ];

    public static function getPriority(string $category): string
    {
        return self::$rankings[$category] ?? 'low';
    }

    public static function getNumericPriority(string $category): int
    {
        return self::$numericRankings[$category] ?? 20;
    }

    /**
     * Get the rankings.
     *
     * @return array
     */
    public static function getRankings(): array
    {
        return self::$rankings;
    }
}