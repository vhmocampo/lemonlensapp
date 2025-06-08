<?php

namespace App\Services;

use App\Services\OpenAIService;
use App\Util\Deslugify;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PremiumChatService
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
     * Get a JSON object from OpenAI for the given vehicle information.
     * Build a prompt here as well
     *
     * @param string $make
     * @param string $model
     * @param integer $year
     * @param integer $mileage
     * @param integer $zipCode
     * @param string $information
     * @param array $complaints
     * @param integer $currentScore
     * @return array
     */
    public function __invoke(
        string $make, string $model, int $year, int $mileage, int $zipCode, ?string $information = null, ?string $listingHtml = null): array
    {
        // Normalize the make and model
        $normalizedMake = Str::slug($make);
        $normalizedModel = Str::slug($model);

        // Expected fields
        $expectedFields = [
            'score' => 'Reliability score, adjusted for mileage, from 0 to 100, where 100 is the best possible score', 
            'suggestions' => 'if purchased, a list of 3 suggestions over the next five years, formatted as friendly pro-tips',
            'cost_from' => 'expected low range of upcoming repair cost, within the next 48 months, using the amounts from the given repairs, if any', 
            'cost_to' => 'expected upper range of upcoming repair costs, within the next 48 months, using the amounts from the given repairs, if any',
            'summary' => 'summarize the vehicle; if i provided relevant information, analyze it and include it in the summary, otherwise just summarize the vehicle based on the make, model, year, mileage, and zip code', 
            'checklist' => 'an array of 5 or less items that I should check when inspecting or buying the vehicle, in laymen terms, specifically what I should look for and why it matters', 
            'repairs' => 'an array of 5 or less likely upcoming repairs (excluding recalls), sorted by likelihood, I might need to do, with these fields (always include costs) -- if i provided relevant information, include that as context for which repairs might be likely: description (140 words), cost_range_from (int), cost_range_to (int), average_cost (int), expected_mileage (int), mileage_range_from (int), mileage_range_to (int), likelihood (percentage), name (technical mechanic term), example_complaint, times_reported (string) which is how frequently this repair is reported by other owners, in laymen terms',
            'questions' => 'an array of 5 or less questions I should ask the dealer, in laymen terms. avoid generic questions, avoid questions like "Is this vehicle reliable?" or "What is the history of this vehicle?"',
            'known_issues' => 'an array of known issues for the vehicle, if any, otherwise provide empty array, with these fields: critical (boolean), description',
            'recalls' => 'an array of recalls for the vehicle, if any (otherwise provide empty array), with these fields: critical (boolean), description, recall_date',
            "sources" => 'a text list of sources, comma separated, just a string, that were used to generate this report, for example: "NHTSA, Edmunds, Consumer Reports"',
        ];

        $format = "Response format should be a JSON object with the following fields:\n";
        foreach ($expectedFields as $field => $description) {
            $format .= "- `{$field}`: {$description}\n";
        }

        // If the listing HTML is provided, include it in the prompt
        if (!empty($listingHtml)) {
            $listingHtml = strip_tags($listingHtml); // Remove HTML tags for better readability
            $listingHtml = Str::limit($listingHtml, 20000); // Limit to 500 characters for brevity
            $information .= "\n\nListing HTML: {$listingHtml}";
        }

        // Build the prompt
        $prompt = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant providing detailed, easy-to-understand vehicle information directly to the person asking. Speak to them as "you" and "your." Use the provided vehicle make, model, year, mileage, zip code, and any extra details to generate a comprehensive, localized report. Write as if you\'re speaking directly to the vehicle buyer or owner, not about them. Do not refer to "the user" or "customer"—always address the reader directly.'
            ],
            [
                'role' => 'user',
                'content' => "Provide to me, a layman, a detailed localized report for the vehicle: {$year} {$normalizedMake} {$normalizedModel}, " .
                             "Mileage: {$mileage}, Zip Code: {$zipCode}. " .
                             "Here is some information I know (might not be relevant): {$information} " .
                                " {$format} "
            ]
        ];

        // Call OpenAI service to get the response
        $response = $this->openAIService->chat($prompt, 'gpt-4.1-2025-04-14');

        if (!is_array($response)) {
            Log::error('PremiumChatService: OpenAI response is not an array', [
                'response' => $response
            ]);
            // If the response starts with "```json", remove that part
            if (str_starts_with($response, '```json')) {
                $response = substr($response, 8); // Remove the "```json" part
            }

            // Remove any trailing "```" if it exists
            if (str_ends_with($response, '```')) {
                $response = substr($response, 0, -3); // Remove the trailing "```"
            }

            $response = trim($response); // Trim any extra whitespace
            $response = json_decode($response, true);
        }

        // Parse the response into an array
        return $response ?? [];
    }
}