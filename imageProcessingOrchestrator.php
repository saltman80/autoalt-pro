<?php

use Psr\Log\LoggerInterface;
use ApiClient;
use DataStore;
use ApiClientException;
use DataStoreException;

class ImageProcessingOrchestrator
{
    /** @var ApiClient */
    private $apiClient;

    /** @var DataStore */
    private $dataStore;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(ApiClient $apiClient, DataStore $dataStore, LoggerInterface $logger)
    {
        $this->apiClient  = $apiClient;
        $this->dataStore  = $dataStore;
        $this->logger     = $logger;
    }

    /**
     * Process a list of image attachment IDs: generate alt text and captions, then save.
     *
     * @param int[] $imageIds
     * @return array<string, array<string, mixed>> Results keyed by original input ID as string.
     *     Each entry contains:
     *       - success: bool
     *       - alt_text: string (on success)
     *       - caption: string (on success)
     *       - error: string (on failure)
     */
    public function processImages(array $imageIds): array
    {
        $results = [];

        foreach ($imageIds as $inputId) {
            $key = (string) $inputId;

            // Validate that the ID is an integer or numeric string
            if (!(is_int($inputId) || ctype_digit((string) $inputId))) {
                $this->logger->warning('Invalid image ID provided', ['id' => $inputId]);
                $results[$key] = [
                    'success' => false,
                    'error'   => 'invalid_id',
                ];
                continue;
            }

            $attachmentId = (int) $inputId;
            // Ensure positive, non-zero ID
            if ($attachmentId < 1) {
                $this->logger->warning('Invalid image ID (must be positive non-zero)', ['attachment_id' => $attachmentId]);
                $results[$key] = [
                    'success' => false,
                    'error'   => 'invalid_id',
                ];
                continue;
            }

            $url = wp_get_attachment_url($attachmentId);
            if ($url === false) {
                $this->logger->error('Failed to retrieve URL for attachment', ['attachment_id' => $attachmentId]);
                $results[$key] = [
                    'success' => false,
                    'error'   => 'url_not_found',
                ];
                continue;
            }

            try {
                $analysis = $this->apiClient->analyzeImage($url);
            } catch (ApiClientException $e) {
                $this->logger->error('API analysis failed', [
                    'attachment_id' => $attachmentId,
                    'message'       => $e->getMessage(),
                ]);
                $results[$key] = [
                    'success' => false,
                    'error'   => 'api_error',
                ];
                continue;
            }

            $altText = isset($analysis['alt_text']) ? sanitize_text_field($analysis['alt_text']) : '';
            $caption = isset($analysis['caption']) ? wp_kses_post($analysis['caption']) : '';

            if ($altText === '') {
                $this->logger->warning('Empty alt text returned from API', ['attachment_id' => $attachmentId]);
            }

            try {
                $this->dataStore->saveAltText($attachmentId, $altText, $caption);
                $results[$key] = [
                    'success'  => true,
                    'alt_text' => $altText,
                    'caption'  => $caption,
                ];
            } catch (DataStoreException $e) {
                $this->logger->error('Failed to save alt text', [
                    'attachment_id' => $attachmentId,
                    'message'       => $e->getMessage(),
                ]);
                $results[$key] = [
                    'success' => false,
                    'error'   => 'save_failed',
                ];
            }
        }

        return $results;
    }
}