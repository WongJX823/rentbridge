<?php
/**
 * Race-preference helpers, shared by property listings and housemate posts.
 * Stored preference values: 'any' | 'malay' | 'chinese' | 'indian' | 'others'.
 * Student identity (students.race): 'malay' | 'chinese' | 'indian' | 'others' | NULL.
 */

/** Allowed stored preference values. */
function rb_race_values(): array { return ['any', 'malay', 'chinese', 'indian', 'others']; }

/** Normalise any input to a valid preference value (defaults to 'any'). */
function rb_race_norm(?string $v): string
{
    $v = strtolower(trim((string)$v));
    return in_array($v, rb_race_values(), true) ? $v : 'any';
}

/** Normalise a student's own race to a stored value, or '' when unset/invalid. */
function rb_race_identity_norm(?string $v): string
{
    $v = strtolower(trim((string)$v));
    return in_array($v, ['malay', 'chinese', 'indian', 'others'], true) ? $v : '';
}

/** preference value => human label, for <select> options. */
function rb_race_options(): array
{
    return [
        'any'     => 'Any / no preference',
        'malay'   => 'Malay only',
        'chinese' => 'Chinese only',
        'indian'  => 'Indian only',
        'others'  => 'Others only',
    ];
}

/** value => label for a student's own race identity ('' = prefer not to say). */
function rb_race_identity_options(): array
{
    return [
        ''        => 'Prefer not to say',
        'malay'   => 'Malay',
        'chinese' => 'Chinese',
        'indian'  => 'Indian',
        'others'  => 'Others',
    ];
}

/** Short label for a stored preference value. */
function rb_race_label(string $v): string
{
    return rb_race_options()[rb_race_norm($v)] ?? 'Any / no preference';
}

/**
 * Render a Bootstrap badge for a race preference. Returns '' for 'any'
 * (nothing to show when there is no restriction).
 */
function rb_race_badge(string $v): string
{
    $v = rb_race_norm($v);
    if ($v === 'any') return '';
    $word = ['malay' => 'Malay', 'chinese' => 'Chinese', 'indian' => 'Indian', 'others' => 'Others'][$v] ?? ucfirst($v);
    return '<span class="badge bg-light text-dark border">'
         . '<i class="bi bi-people me-1"></i>' . $word . ' only</span>';
}
