<?php
/**
 * Gender-preference helpers, shared by property listings and housemate posts.
 * Stored values: 'any' | 'male' | 'female'.
 */

/** Allowed stored values. */
function rb_gender_values(): array { return ['any', 'male', 'female']; }

/** Normalise any input to a valid stored value (defaults to 'any'). */
function rb_gender_norm(?string $v): string
{
    $v = strtolower(trim((string)$v));
    return in_array($v, rb_gender_values(), true) ? $v : 'any';
}

/** value => human label, for <select> options and captions. */
function rb_gender_options(string $context = 'tenant'): array
{
    return [
        'any'    => 'Any / no preference',
        'male'   => 'Male only',
        'female' => 'Female only',
    ];
}

/** Short label for a stored value. */
function rb_gender_label(string $v): string
{
    return rb_gender_options()[rb_gender_norm($v)] ?? 'Any / no preference';
}

/**
 * Render a Bootstrap badge for a gender preference. Returns '' for 'any'
 * (nothing to show when there is no restriction).
 */
function rb_gender_badge(string $v): string
{
    $v = rb_gender_norm($v);
    if ($v === 'any') return '';
    $icon = $v === 'male' ? 'bi-gender-male' : 'bi-gender-female';
    $cls  = $v === 'male' ? 'text-primary' : 'text-danger';
    $txt  = $v === 'male' ? 'Male only' : 'Female only';
    return '<span class="badge bg-light ' . $cls . ' border">'
         . '<i class="bi ' . $icon . ' me-1"></i>' . $txt . '</span>';
}
