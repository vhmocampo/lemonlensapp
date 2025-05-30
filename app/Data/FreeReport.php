<?php

namespace App\Data;

/**
 * 'score' => $score,
    'summary' => $vehicle->content['summary'] ?? '',
    'complaints' => $complaints,
    'cost_from' => $costFrom,
    'cost_to' => $costTo,
    'recalls' => $vehicle->getRecalls() ?? [
        [
            'description' => 'No recalls/critical issues found, consider a premium report for this vehicle for more information',
            'priority' => 2,
        ]
    ],
    'known_issues' => $vehicle->getKnownIssues() ?? [
        [
            'description' => 'No known issues found, consider a premium report for this vehicle for more information',
            'priority' => 2,
        ]
    ],
    'suggestions' => $vehicle->getSuggestions() ?? [
        [
            'description' => 'Regular maintenance extends the lifespan of this vehicle significantly',
            'priority' => 2,
        ],
        [
            'description' => 'Change the oil and filter regularly, and check the air filter',
            'priority' => 2,
        ],
        [
            'description' => 'Check the maintenance schedule and follow it',
            'priority' => 2,
        ]
    ],
 */

class FreeReport
{
    public $score;
    public $recommendation;
    public $summary;
    public $complaints;
    public $cost_from;
    public $cost_to;
    public $recalls;
    public $known_issues;
    public $suggestions;

    public function __construct($score = null, $recommendation = null, $summary = null, $complaints = null, $cost_from = null, $cost_to = null, $recalls = null, $known_issues = null, $suggestions = null)
    {
        $this->score = $score;
        $this->recommendation = $recommendation;
        $this->summary = $summary;
        $this->complaints = $complaints;
        $this->cost_from = $cost_from;
        $this->cost_to = $cost_to;
        $this->recalls = $recalls;
        $this->known_issues = $known_issues;
        $this->suggestions = $suggestions;
    }

    public function fromArray(array $data)
    {
        $this->score = $data['score'];
        $this->recommendation = $data['recommendation'];
        $this->summary = $data['summary'];
        $this->complaints = $data['complaints'];
        $this->cost_from = $data['cost_from'];
        $this->cost_to = $data['cost_to'];
        $this->recalls = $data['recalls'];
        $this->known_issues = $data['known_issues'];
        $this->suggestions = $data['suggestions'];
    }

    public function toArray()
    {
        return [
            'score' => $this->score,
            'recommendation' => $this->recommendation,
            'summary' => $this->summary,
            'complaints' => $this->complaints,
            'cost_from' => $this->cost_from,
            'cost_to' => $this->cost_to,
            'recalls' => $this->recalls,
            'known_issues' => $this->known_issues,
            'suggestions' => $this->suggestions,
        ];
    }
}