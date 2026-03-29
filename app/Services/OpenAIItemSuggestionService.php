<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class OpenAIItemSuggestionService
{
    private ?string $lastErrorCode = null;

    public function __construct(private array $config)
    {
    }

    public function lastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /**
     * @param list<string> $preferredCategories
     * @param list<string> $preferredTags
     * @return array{title:string,category:string,description:string,tags:list<string>}|null
     */
    public function suggestFromImageDataUrl(string $imageDataUrl, array $preferredCategories, array $preferredTags): ?array
    {
        $this->lastErrorCode = null;

        $apiKey = trim((string) ($this->config['openai']['api_key'] ?? ''));
        if ($apiKey === '') {
            $this->lastErrorCode = 'missing_api_key';
            return null;
        }

        $baseUrl = rtrim((string) ($this->config['openai']['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $model = (string) ($this->config['openai']['model'] ?? 'gpt-4.1-mini');

        $prompt = $this->buildPrompt($preferredCategories, $preferredTags);
        $payload = [
            'model' => $model,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $prompt],
                    ['type' => 'input_image', 'image_url' => $imageDataUrl],
                ],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'item_suggestion',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'category' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'category', 'description', 'tags'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_output_tokens' => 350,
        ];

        $ch = curl_init($baseUrl . '/responses');
        if ($ch === false) {
            throw new RuntimeException('OpenAI-Verbindung konnte nicht initialisiert werden.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw) || $raw === '') {
            throw new RuntimeException('OpenAI-Request fehlgeschlagen: ' . $error);
        }

        if ($status < 200 || $status >= 300) {
            $errorPayload = json_decode($raw, true);
            $error = is_array($errorPayload['error'] ?? null) ? $errorPayload['error'] : [];
            $errorType = (string) ($error['type'] ?? '');
            $errorCode = (string) ($error['code'] ?? '');
            $errorMessage = (string) ($error['message'] ?? '');

            if ($status === 429 && $errorCode === 'insufficient_quota') {
                $this->lastErrorCode = 'insufficient_quota';
            } elseif ($status === 429) {
                $this->lastErrorCode = 'rate_limited';
            } elseif ($status === 401 || $status === 403) {
                $this->lastErrorCode = 'auth_error';
            } else {
                $this->lastErrorCode = 'api_error';
            }

            Logger::warning('OpenAI returned non-success status', [
                'status' => $status,
                'error_type' => $errorType,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);
            return null;
        }

        $response = json_decode($raw, true);
        if (!is_array($response)) {
            Logger::warning('OpenAI response is not valid JSON');
            $this->lastErrorCode = 'invalid_response';
            return null;
        }

        $content = $this->extractOutputText($response);
        if ($content === null || $content === '') {
            Logger::warning('OpenAI response did not include output text');
            $this->lastErrorCode = 'invalid_response';
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            Logger::warning('OpenAI content is not valid suggestion JSON');
            $this->lastErrorCode = 'invalid_response';
            return null;
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $category = trim((string) ($decoded['category'] ?? ''));
        $description = trim((string) ($decoded['description'] ?? ''));
        $tags = is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [];
        $tags = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $tags), static fn (string $value): bool => $value !== ''));

        if ($title === '' && $category === '' && $description === '' && $tags === []) {
            return null;
        }

        return [
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'tags' => $tags,
        ];
    }
    /**
     * @param list<string> $preferredCategories
     * @param list<string> $preferredTags
     */
    private function buildPrompt(array $preferredCategories, array $preferredTags): string
    {
        $categoryList = $preferredCategories === [] ? 'Keine Kategorien vorhanden.' : implode(', ', $preferredCategories);
        $tagList = $preferredTags === [] ? 'Keine vorhandenen Tags.' : implode(', ', $preferredTags);

        return "Du analysierst ein Foto eines Gegenstands in einer Sharing-Webanwendung. "
            . "Antwortsprache: Deutsch. Sei konservativ und erfinde keine technischen Details. "
            . "Liefere nur gültiges JSON passend zum Schema. "
            . "Felder: title (kurz, konkret), category, description (1-3 Sätze, sachlich), tags (Liste). "
            . "Bevorzuge unbedingt bestehende Kategorien und Tags. Neue nur wenn nichts passt. "
            . "Vorhandene Kategorien: {$categoryList}. "
            . "Vorhandene Tags: {$tagList}.";
    }

    private function extractOutputText(array $response): ?string
    {
        if (is_string($response['output_text'] ?? null) && $response['output_text'] !== '') {
            return $response['output_text'];
        }

        $output = $response['output'] ?? null;
        if (!is_array($output)) {
            return null;
        }

        foreach ($output as $segment) {
            $contentList = $segment['content'] ?? null;
            if (!is_array($contentList)) {
                continue;
            }

            foreach ($contentList as $content) {
                $text = $content['text'] ?? null;
                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }
}
