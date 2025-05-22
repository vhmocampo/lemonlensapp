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

    public static function getPriority(string $category): string
    {
        return self::$rankings[$category] ?? 'low';
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