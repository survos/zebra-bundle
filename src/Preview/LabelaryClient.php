<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Preview;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LabelaryClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpoint,
        private ?string $apiKey,
        private float $timeout,
    ) {
    }

    public function render(PreviewRequest $request): PreviewResult
    {
        $headers = [
            'Accept' => $this->resolveAcceptHeader($request->format),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        if ($this->apiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl($request), [
                'headers' => $headers,
                'body' => $request->zpl,
                'timeout' => $this->timeout,
            ]);

            $content = $response->getContent();
            $responseHeaders = $response->getHeaders(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Unable to render Zebra preview via Labelary.', 0, $exception);
        }

        $mimeType = $responseHeaders['content-type'][0] ?? $this->resolveAcceptHeader($request->format);
        $warnings = $this->extractWarnings($responseHeaders['x-warnings'][0] ?? null);

        return new PreviewResult($content, $mimeType, $warnings);
    }

    private function buildUrl(PreviewRequest $request): string
    {
        return sprintf(
            '%s/printers/%ddpmm/labels/%sx%s/%d/',
            rtrim($this->endpoint, '/'),
            $request->dpmm,
            $this->trimFloat($request->widthInches),
            $this->trimFloat($request->heightInches),
            $request->index,
        );
    }

    private function resolveAcceptHeader(string $format): string
    {
        return match ($format) {
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            default => 'image/png',
        };
    }

    /**
     * @return list<string>
     */
    private function extractWarnings(?string $headerValue): array
    {
        if (!$headerValue) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $warning): string => trim($warning),
            explode(',', $headerValue),
        )));
    }

    private function trimFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }
}
