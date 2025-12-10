<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Placeholder for optional request signing verification.
 * Currently a no-op. Can be extended later to verify HMAC signatures.
 */
class ApiRequestSigning
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfg = config('svg.auth.signing', []);
        $enabled = (bool) ($cfg['enabled'] ?? false);
        if (!$enabled) {
            return $next($request);
        }

        $secret = (string) ($cfg['secret'] ?? '');
        $sigHeader = (string) ($cfg['signature_header'] ?? 'X-Signature');
        $tsHeader = (string) ($cfg['timestamp_header'] ?? 'X-Signature-Timestamp');
        $skew = (int) ($cfg['skew'] ?? 300);
        $algo = (string) ($cfg['algo'] ?? 'sha256');

        $providedSig = (string) $request->headers->get($sigHeader, '');
        $timestamp = (string) $request->headers->get($tsHeader, '');

        if ($secret === '' || $providedSig === '' || $timestamp === '') {
            return $this->unauthorized('Missing signature or secret.');
        }

        if (!ctype_digit($timestamp)) {
            return $this->unauthorized('Invalid signature timestamp.');
        }

        $ts = (int) $timestamp;
        $now = time();
        if (abs($now - $ts) > $skew) {
            return $this->unauthorized('Signature timestamp outside allowed window.');
        }

        $payload = $request->getContent();
        $signed = $timestamp . '.' . $payload;
        $computed = hash_hmac($algo, $signed, $secret);

        if (!hash_equals($computed, $providedSig)) {
            return $this->unauthorized('Invalid signature.');
        }

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'invalid_signature',
                'message' => $message,
            ],
        ], 401);
    }
}
