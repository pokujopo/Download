<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AIProviderContract;
use App\Domain\AI\DTOs\ContentGenerationData;
use App\Domain\Social\Capabilities\SocialCapabilities;

final class AIContentService
{
    public function __construct(
        private AIProviderContract $provider,
    ) {
    }

    public function generate(
        string $niche,
        array $topics,
        string $tone,
        SocialCapabilities $capabilities,
        array $research = [],
    ): ContentGenerationData {
        $topic = $topics[array_rand($topics)] ?? $niche;

        $researchContext = collect($research)
            ->map(function ($item) {
                return "- {$item->title}: {$item->summary} ({$item->url})";
            })
            ->implode("\n");

        $contentFormat = match (true) {
            $capabilities->mediaRequired && $capabilities->image
                => 'Write a caption that complements the image.',
            $capabilities->mediaRequired && $capabilities->video
                => 'Write a caption that complements the video.',
            default
                => 'Write a text-first social media post.',
        };

        $prompt = <<<PROMPT
Create one original social media post.

Niche: {$niche}
Topic: {$topic}
Tone: {$tone}

Platform capabilities:
- Text: {$this->bool($capabilities->text)}
- Image: {$this->bool($capabilities->image)}
- Video: {$this->bool($capabilities->video)}
- Caption: {$this->bool($capabilities->caption)}
- Media required: {$this->bool($capabilities->mediaRequired)}

Content format:
{$contentFormat}

Research:
{$researchContext}

Requirements:
- Use the research as factual context.
- Do not copy the source text.
- Do not invent facts that are not supported by the research.
- Make the post useful and engaging.
- Adapt the writing to the platform capabilities.
- Do not mention AI.
- Do not describe, explain, suggest, or generate an image concept.
- Do not include labels such as "Image Concept", "Image Idea", or "Visual Concept".
- Do not include instructions for creating media.
- Return ONLY the final publishable social media caption/post text.
PROMPT;

        $caption = $this->provider->generate($prompt);

        return new ContentGenerationData(
            caption: trim($caption),
            topic: $topic,
        );
    }

    private function bool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}