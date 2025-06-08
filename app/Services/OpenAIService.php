<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIService
{
    /**
     * @var \OpenAI\Client
     */
    private $client;

    /**
     * @var int Cache duration in seconds (default: 1 day)
     */
    private $cacheDuration = 86400;

    /**
     * Create a new OpenAI Service instance.
     */
    public function __construct()
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Generate a chat completion with GPT
     *
     * @param string|array $prompt The prompt or array of messages to send
     * @param string $model The model to use (default: gpt-4-turbo-preview)
     * @param array $options Additional options for the API call
     * @return string The generated response text
     */
    public function chat($prompt, $model = 'gpt-4-turbo-preview', array $options = [])
    {
        try {
            $messages = $this->formatPrompt($prompt);

            // Generate cache key from the request
            $cacheKey = 'openai_chat_' . md5(json_encode([
                'messages' => $messages,
                'model' => $model,
                'options' => $options
            ]));

            // Try to get from cache first
            return Cache::remember($cacheKey, $this->cacheDuration, function() use ($messages, $model, $options) {
                $response = $this->client->chat()->create(array_merge([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 5000,
                ], $options));

                // Log token usage
                if (isset($response->usage->total_tokens)) {
                    Log::info('OpenAI API usage: ' . $response->usage->total_tokens . ' tokens');
                }

                return $response->choices[0]->message->content;
            });
        } catch (\Exception $e) {
            Log::error('OpenAI API error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a completion with a system prompt and user message
     * 
     * @param string $systemPrompt The system instructions
     * @param string $userMessage The user message
     * @param string $model The model to use
     * @param array $options Additional options
     * @return string The generated response
     */
    public function chatWithSystem($systemPrompt, $userMessage, $model = 'gpt-4-turbo-preview', array $options = [])
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ];

        return $this->chat($messages, $model, $options);
    }

    /**
     * Generate repair descriptions for specific issues
     * 
     * @param string $issue The repair issue to describe
     * @param int $wordLimit Optional word limit (default: 150)
     * @return string The repair description
     */
    public function generateRepairDescription($issue, $wordLimit = 150)
    {
        $systemPrompt = "You are an automotive expert providing clear, concise descriptions of car repairs. 
            Explain what the repair involves, why it might be needed, and any relevant details car owners should know.
            Keep explanations informative but accessible to non-experts.";

        $userPrompt = "Please provide a description of the following car repair or maintenance issue: '{$issue}'. 
            Keep your response under {$wordLimit} words.";

        return $this->chatWithSystem($systemPrompt, $userPrompt);
    }

    /**
     * Generate possible maintenance recommendations based on vehicle data
     * 
     * @param array $vehicleData Array containing make, model, year, and mileage
     * @param int $limit Number of recommendations to generate
     * @return array Array of maintenance recommendations
     */
    public function generateMaintenanceRecommendations(array $vehicleData, $limit = 5)
    {
        $make = $vehicleData['make'] ?? 'unknown';
        $model = $vehicleData['model'] ?? 'unknown';
        $year = $vehicleData['year'] ?? 'unknown';
        $mileage = $vehicleData['mileage'] ?? 'unknown';

        $systemPrompt = "You are an expert automotive technician specializing in vehicle maintenance schedules. 
            Provide accurate, helpful maintenance recommendations based on manufacturer guidelines and industry best practices.";

        $userPrompt = "What are the {$limit} most important maintenance items for a {$year} {$make} {$model} with {$mileage} miles? 
            For each item, provide: 1) The maintenance task, 2) Why it's needed at this mileage, 
            3) Potential consequences of neglecting this maintenance. Format as JSON.";

        $options = [
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->chatWithSystem($systemPrompt, $userPrompt, 'gpt-4-turbo-preview', $options);

        // Decode JSON response
        $recommendations = json_decode($response, true);

        // If decoding failed or unexpected format, return empty array
        if (!is_array($recommendations) || empty($recommendations['recommendations'])) {
            return [];
        }

        return $recommendations['recommendations'];
    }

    /**
     * Format the prompt into the expected messages array format
     * 
     * @param string|array $prompt
     * @return array
     */
    private function formatPrompt($prompt)
    {
        // If already formatted as messages array, return as is
        if (is_array($prompt) && isset($prompt[0]['role'])) {
            return $prompt;
        }

        // If string, convert to user message
        if (is_string($prompt)) {
            return [
                ['role' => 'user', 'content' => $prompt]
            ];
        }

        throw new \InvalidArgumentException('Invalid prompt format');
    }
}