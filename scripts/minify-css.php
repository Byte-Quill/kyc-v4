<?php
/**
 * scripts/minify-css.php — generate assets/style.min.css from style.css.
 *
 * Safe, dependency-free minifier: strips comments and collapses whitespace
 * while preserving quoted strings, url(...) tokens and calc() expressions.
 * Run from the project root:  php scripts/minify-css.php
 * The readable source (style.css) stays untouched — this only writes the
 * .min.css sidecar that layout.php serves when it is up to date.
 */
declare(strict_types=1);

$src = __DIR__ . '/../assets/style.css';
$out = __DIR__ . '/../assets/style.min.css';

$css = (string) file_get_contents($src);
if ($css === '') {
    fwrite(STDERR, "Cannot read $src\n");
    exit(1);
}

// Protect quoted strings and url(...) tokens from every later step.
$tokens = [];
$css = preg_replace_callback(
    '/"([^"\\\\]|\\\\.)*"|\'([^\'\\\\]|\\\\.)*\'|url\([^)]*\)/',
    function (array $m) use (&$tokens): string {
        $tokens[] = $m[0];
        return "\x01" . (count($tokens) - 1) . "\x01";
    },
    $css
) ?? $css;

// Remove comments.
$css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;

// Collapse whitespace: newlines and runs of spaces/tabs -> single space.
$css = preg_replace('/\s+/', ' ', $css) ?? $css;

// Remove spaces around structural characters that never need them.
$css = str_replace([' {', '{ ', ' }', '} ', '; ', ': ', ', ', ' > '], ['{', '{', '}', '}', ';', ':', ',', '>'], $css);

// Drop the final rule separator and trim.
$css = trim(str_replace(';}', '}', $css));

// Restore the protected strings/tokens.
$css = preg_replace_callback(
    '/\x01(\d+)\x01/',
    fn (array $m): string => $tokens[(int) $m[1]],
    $css
) ?? $css;

if (@file_put_contents($out, $css) === false) {
    fwrite(STDERR, "Cannot write $out\n");
    exit(1);
}

printf("style.css %d bytes -> style.min.css %d bytes (%.1f%% smaller)\n",
    filesize($src),
    filesize($out),
    (1 - filesize($out) / filesize($src)) * 100
);
