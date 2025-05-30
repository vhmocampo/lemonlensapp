<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Vehicle extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicles';
    protected $primaryKey = '_id';

    protected $fillable = [
        'make',
        'model',
        'year',
    ];

    public function getRecalls()
    {
        $recalls = $this->content['recalls'] ?? null;
        if (!$recalls) {
            return null;
        }
        $recalls = array_map(function($recall) {
            if (str_contains($recall, 'critical')) {
                $priority = 1;
            } else {
                $priority = 2;
            }
            return [
                'description' => $recall,
                'priority' => $priority,
            ];
        }, $recalls);
        return $recalls;
    }

    public function getKnownIssues()
    {
        $knownIssues = $this->content['known_issues'] ?? null;
        if (!$knownIssues) {
            return null;
        }
        $knownIssues = array_map(function($knownIssue) {
            if (str_contains($knownIssue, 'critical')) {
                $priority = 1;
            } else {
                $priority = 2;
            }
            return [
                'description' => $knownIssue,
                'priority' => $priority,
            ];
        }, $knownIssues);
        return $knownIssues;
    }

    public function getSuggestions()
    {
        $suggestions = $this->content['suggestions'] ?? null;
        if (!$suggestions) {
            return null;
        }
        return $suggestions;
    }

    public function getSummary()
    {
        $summary = $this->content['summary'] ?? '';
        return $summary;
    }
}