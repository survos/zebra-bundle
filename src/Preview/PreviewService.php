<?php

declare(strict_types=1);

namespace Survos\ZebraBundle\Preview;

use Psr\Cache\CacheItemPoolInterface;

final readonly class PreviewService implements PreviewServiceInterface
{
    public function __construct(
        private LabelaryClient $labelaryClient,
        private CacheItemPoolInterface $cache,
        private bool $cacheEnabled,
        private int $cacheTtl,
    ) {
    }

    public function preview(PreviewRequest $request): PreviewResult
    {
        if (!$this->cacheEnabled) {
            return $this->labelaryClient->render($request);
        }

        $item = $this->cache->getItem('zebra_preview_' . $request->cacheKey());
        if ($item->isHit()) {
            /** @var array{content: string, mimeType: string, warnings: list<string>} $payload */
            $payload = $item->get();

            return new PreviewResult(
                $payload['content'],
                $payload['mimeType'],
                $payload['warnings'],
            );
        }

        $result = $this->labelaryClient->render($request);
        $item->set([
            'content' => $result->content,
            'mimeType' => $result->mimeType,
            'warnings' => $result->warnings,
        ]);
        $item->expiresAfter($this->cacheTtl);
        $this->cache->save($item);

        return $result;
    }
}
