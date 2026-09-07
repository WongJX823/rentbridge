<?php
/**
 * Race-preference helpers, shared by property listings and housemate posts.
 * Stored preference values: '' (any / no restriction) or a comma-separated
 * subset of 'malay','chinese','indian','others' — a landlord/poster can
 * restrict to one or several races at once (backed by a MySQL SET column).
 * Legacy single-value 'any' (pre-multiselect) is treated the same as ''.
 * Student identity (students.race): 'malay' | 'chinese' | 'indian' | 'others' | NULL.
 */

/** Allowed individual race values (excluding the "any" / empty case). */
function rb_race_values(): array { return ['malay', 'chinese', 'indian', 'others']; }

/** value => label, for checkboxes and badges. */
function rb_race_labels(): array
{
    return [
        'malay'   => 'Malay',
        'chinese' => 'Chinese',
        'indian'  => 'Indian',
        'others'  => 'Others',
    ];
}

/**
 * Normalise input — an array of checked values (from a checkbox group) or a
 * stored comma-separated string — to a canonical comma-separated string of
 * valid values, deduped and in a fixed order. Empty / none selected => ''
 * (means "any / no restriction"). Legacy 'any' string collapses to ''.
 *
 * @param array|string|null $input
 */
function rb_race_norm($input): string
{
    if ($input === null) $input = [];
    if (is_string($input)) {
        if (strtolower(trim($input)) === 'any') return '';
        $input = explode(',', $input);
    }
    $input = array_map(fn($v) => strtolower(trim((string)$v)), $input);
    $valid = array_values(array_intersect(rb_race_values(), $input));
    return implode(',', $valid);
}

/** Explode a stored preference string into an array of race values. */
function rb_race_list(string $v): array
{
    $v = trim($v);
    return $v === '' ? [] : explode(',', $v);
}

/** Whether $v represents "any / no restriction" (empty selection). */
function rb_race_is_any(string $v): bool { return rb_race_norm($v) === ''; }

/**
 * True if a stored preference allows $viewerRace — either the preference is
 * "any", the viewer's own race is unknown (not gated), or the viewer's race
 * is one of the selected values.
 */
function rb_race_matches(string $v, ?string $viewerRace): bool
{
    $v = rb_race_norm($v);
    if ($v === '' || empty($viewerRace)) return true;
    return in_array(strtolower(trim($viewerRace)), rb_race_list($v), true);
}

/** Normalise a student's own race to a stored value, or '' when unset/invalid. */
function rb_race_identity_norm(?string $v): string
{
    $v = strtolower(trim((string)$v));
    return in_array($v, rb_race_values(), true) ? $v : '';
}

/** value => label for a student's own race identity ('' = prefer not to say). */
function rb_race_identity_options(): array
{
    return ['' => 'Prefer not to say'] + rb_race_labels();
}

/**
 * Human label for a stored preference, e.g. "Malay / Chinese only".
 * '' (any) => 'Any / no preference'.
 */
function rb_race_label(string $v): string
{
    $list = rb_race_list(rb_race_norm($v));
    if (empty($list)) return 'Any / no preference';
    $labels = rb_race_labels();
    return implode(' / ', array_map(fn($r) => $labels[$r] ?? ucfirst($r), $list)) . ' only';
}

/**
 * Render a Bootstrap badge for a race preference. Returns '' for "any"
 * (nothing to show when there is no restriction).
 */
function rb_race_badge(string $v): string
{
    if (rb_race_is_any($v)) return '';
    return '<span class="badge bg-light text-dark border">'
         . '<i class="bi bi-people me-1"></i>' . e(rb_race_label($v)) . '</span>';
}

/**
 * Render a Bootstrap toggle-button checkbox group for selecting one or more
 * preferred races. Inputs post as "{$name}[]" — read with
 * rb_race_norm($_POST[$name] ?? []).
 */
function rb_race_checkboxes_field(string $name, string $selectedCsv): void
{
    $selected = rb_race_list(rb_race_norm($selectedCsv));
    echo '<div class="d-flex flex-wrap gap-2">';
    foreach (rb_race_labels() as $rv => $rlabel) {
        $id = $name . '_' . $rv;
        $checked = in_array($rv, $selected, true) ? ' checked' : '';
        echo '<input type="checkbox" class="btn-check" name="' . e($name) . '[]" id="' . e($id) . '" '
           . 'value="' . e($rv) . '" autocomplete="off"' . $checked . '>';
        echo '<label class="btn btn-outline-secondary btn-sm" for="' . e($id) . '">' . e($rlabel) . '</label>';
    }
    echo '</div>';
}
