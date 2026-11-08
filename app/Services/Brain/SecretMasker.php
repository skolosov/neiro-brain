<?php

declare(strict_types=1);

namespace App\Services\Brain;

use App\Services\Brain\Dto\MaskResult;
use App\Settings\SecuritySettings;

/**
 * Removes credentials from text before it reaches the knowledge base.
 *
 * Masking happens on ingestion and again before saving AI output, because a
 * model can repeat a secret it saw in the transcript.
 */
final class SecretMasker
{
    public function __construct(
        private readonly SecuritySettings $settings,
    ) {}

    public function mask(string $text): MaskResult
    {
        if (! $this->settings->mask_secrets) {
            return new MaskResult($text, [], 0);
        }

        $redactions = [];
        $masked = $text;

        foreach ($this->patterns() as $type => $pattern) {
            $masked = preg_replace_callback(
                $pattern,
                function (array $matches) use ($type, &$redactions): string {
                    $redactions[$type] = ($redactions[$type] ?? 0) + 1;

                    return $this->redaction($type, $matches);
                },
                $masked,
            ) ?? $masked;
        }

        if ($this->settings->mask_emails) {
            $masked = preg_replace_callback(
                '/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/u',
                function (array $matches) use (&$redactions): string {
                    $redactions['email'] = ($redactions['email'] ?? 0) + 1;

                    return $this->settings->redaction;
                },
                $masked,
            ) ?? $masked;
        }

        return new MaskResult($masked, $redactions, array_sum($redactions));
    }

    /**
     * @return array<string, string>
     */
    private function patterns(): array
    {
        $patterns = [
            'private_key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
            'anthropic_key' => '/\bsk-ant-[A-Za-z0-9_\-]{20,}\b/',
            'openai_key' => '/\bsk-(?:proj-)?[A-Za-z0-9_\-]{20,}\b/',
            'github_token' => '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}\b/',
            'github_pat' => '/\bgithub_pat_[A-Za-z0-9_]{20,}\b/',
            'gitlab_token' => '/\bglpat-[A-Za-z0-9_\-]{20,}\b/',
            'aws_key' => '/\bAKIA[0-9A-Z]{16}\b/',
            'google_key' => '/\bAIza[0-9A-Za-z_\-]{35}\b/',
            'slack_token' => '/\bxox[baprs]-[A-Za-z0-9\-]{10,}\b/',
            'telegram_token' => '/\b\d{8,10}:[A-Za-z0-9_\-]{35}\b/',
            'jwt' => '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',
            'bearer' => '/\bBearer\s+[A-Za-z0-9\-._~+\/]{20,}=*/i',
            'database_url' => '/\b(?:postgres(?:ql)?|mysql|mongodb(?:\+srv)?|redis):\/\/[^:\s\/]+:[^@\s]+@/i',
            'env_assignment' => '/\b([A-Z0-9_]*(?:PASSWORD|PASSWD|SECRET|TOKEN|API_KEY|PRIVATE_KEY|ACCESS_KEY)[A-Z0-9_]*)\s*=\s*(\'[^\']*\'|"[^"]*"|[^\s,;]+)/',
        ];

        foreach ($this->settings->custom_patterns as $custom) {
            if (! is_string($custom) || trim($custom) === '') {
                continue;
            }

            $delimiter = '#';
            $pattern = $delimiter.str_replace($delimiter, '\\'.$delimiter, $custom).$delimiter.'u';

            if (@preg_match($pattern, '') !== false) {
                $patterns['custom'] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * @param  array<int|string, string>  $matches
     */
    private function redaction(string $type, array $matches): string
    {
        if ($type === 'database_url') {
            return preg_replace('/\/\/[^:\/]+:[^@]+@/', '//'.$this->settings->redaction.'@', $matches[0]) ?? $matches[0];
        }

        if ($type === 'env_assignment') {
            return $matches[1].'='.$this->settings->redaction;
        }

        if ($type === 'bearer') {
            return 'Bearer '.$this->settings->redaction;
        }

        return $this->settings->redaction;
    }
}
