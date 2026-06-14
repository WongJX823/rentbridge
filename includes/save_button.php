<?php
/**
 * Render a heart save button for a property.
 * @param int  $propertyId
 * @param bool $isSaved
 * @param string $size  'sm' | 'md' | 'lg'
 * @param string $position  'inline' | 'overlay'  (overlay = floating on image)
 */
function render_save_button(int $propertyId, bool $isSaved = false, string $size = 'md', string $position = 'inline'): void {
    $iconSize = match($size) {
        'sm' => '1rem',
        'lg' => '1.5rem',
        default => '1.2rem',
    };
    $btnPadding = match($size) {
        'sm' => '4px 8px',
        'lg' => '10px 14px',
        default => '6px 10px',
    };

    $positionStyle = $position === 'overlay'
        ? 'position:absolute; top:8px; right:8px; z-index:5; background:rgba(255,255,255,0.95); backdrop-filter: blur(4px);'
        : '';

    // Detect if user not logged in → button will redirect to login modal
    $isLoggedIn = function_exists('is_logged_in') && is_logged_in();
    ?>
    <button type="button"
            class="save-property-btn btn"
            data-property-id="<?= (int)$propertyId ?>"
            data-saved="<?= $isSaved ? '1' : '0' ?>"
            data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>"
            title="<?= $isSaved ? 'Remove from saved' : 'Save this property' ?>"
            aria-label="<?= $isSaved ? 'Remove from saved' : 'Save this property' ?>"
            style="<?= $positionStyle ?> padding: <?= $btnPadding ?>; border: 1px solid rgba(15,44,82,0.15); background: white; border-radius: 999px; transition: all 0.15s; cursor: pointer;">
        <i class="bi <?= $isSaved ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary' ?>"
           style="font-size: <?= $iconSize ?>;"></i>
    </button>
    <?php
}

/**
 * Render the shared JS that handles all save buttons on a page.
 * Call this ONCE at the bottom of any page that uses render_save_button().
 */
function render_save_button_script(): void {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    ?>
    <script>
    (function() {
        const csrfToken = '<?= function_exists('csrf_token') ? csrf_token() : '' ?>';

        document.body.addEventListener('click', async function(e) {
            const btn = e.target.closest('.save-property-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            // Guest? prompt login
            if (btn.dataset.loggedIn === '0') {
                if (window.bootstrap && document.getElementById('loginPromptModal')) {
                    new bootstrap.Modal(document.getElementById('loginPromptModal')).show();
                } else {
                    window.location.href = '/rentbridge/auth/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                }
                return;
            }

            const propertyId = btn.dataset.propertyId;
            const wasSaved = btn.dataset.saved === '1';
            const icon = btn.querySelector('i');

            // Optimistic UI
            btn.disabled = true;
            if (wasSaved) {
                icon.classList.remove('bi-heart-fill', 'text-danger');
                icon.classList.add('bi-heart', 'text-secondary');
                btn.dataset.saved = '0';
                btn.title = 'Save this property';
            } else {
                icon.classList.remove('bi-heart', 'text-secondary');
                icon.classList.add('bi-heart-fill', 'text-danger');
                btn.dataset.saved = '1';
                btn.title = 'Remove from saved';
            }

            try {
                const response = await fetch('/rentbridge/toggle_saved.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        _csrf: csrfToken,
                        property_id: propertyId,
                        action: 'toggle',
                    })
                });
                const data = await response.json();
                if (!data.ok) throw new Error(data.error || 'Failed');
            } catch (err) {
                // Revert on failure
                btn.dataset.saved = wasSaved ? '1' : '0';
                if (wasSaved) {
                    icon.classList.remove('bi-heart', 'text-secondary');
                    icon.classList.add('bi-heart-fill', 'text-danger');
                } else {
                    icon.classList.remove('bi-heart-fill', 'text-danger');
                    icon.classList.add('bi-heart', 'text-secondary');
                }
                alert('Failed to update saved status. Please try again.');
            } finally {
                btn.disabled = false;
            }
        });
    })();
    </script>
    <?php
}