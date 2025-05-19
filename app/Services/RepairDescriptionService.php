<?php

namespace App\Services;

use App\Models\RepairDescription;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RepairDescriptionService
{
    /**
     * @var OpenAIService
     */
    protected $openAIService;

    /**
     * Create a new repair description service instance.
     * 
     * @param OpenAIService $openAIService
     */
    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Get a repair description for the given slug.
     * If the description doesn't exist, generate it using OpenAI and store it.
     * 
     * @param string $slug The repair term slug
     * @return string The repair description
     */
    public function __invoke(string $slug): string
    {
        // Normalize the slug
        $normalizedSlug = Str::slug($slug);

        // Check if the description already exists
        $repairDescription = RepairDescription::findBySlug($normalizedSlug);

        if ($repairDescription && $repairDescription->description) {
            Log::info("Retrieved existing repair description for: {$normalizedSlug}");
            return $repairDescription->description;
        }

        // If not, we need to generate it
        $term = $this->delugify($normalizedSlug);

        try {
            Log::info("Generating repair description for: {$term}");
            $description = $this->openAIService->generateRepairDescription($term, 50);

            // Save the generated description to the database
            RepairDescription::updateOrCreateBySlug(
                $normalizedSlug,
                $description
            );

            return $description;
        } catch (\Exception $e) {
            Log::error("Failed to generate repair description for {$term}: {$e->getMessage()}");

            // Return a fallback message if generation fails
            return "Information about {$term} is currently unavailable. Please try again later.";
        }
    }

    /**
     * Convert a slug into a human-readable repair term.
     * 
     * @param string $slug The slug to convert
     * @return string The human-readable term
     */
    protected function delugify(string $slug): string
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