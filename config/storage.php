<?php
/**
 * Cloudflare R2 (S3-compatible) object storage configuration.
 *
 * Render's disk is ephemeral — anything written to uploads/ is lost on every
 * restart/redeploy. R2 gives uploads a permanent home. All real values come
 * from environment variables (set in Render's dashboard); the fallbacks here
 * are intentionally empty, so local XAMPP dev with no env vars set just
 * degrades to reading/writing the local uploads/ folder (includes/storage.php
 * handles that fallback) — exactly like before R2 existed.
 *
 * Unlike config/google.php / config/openai.php, this file is NOT git-ignored
 * and must never have real credentials pasted into it directly — it has to
 * exist in the deployed repo for includes/storage.php's require_once to
 * succeed at all. Set real values via env vars, including for local testing
 * (e.g. a local .env loader, or just export them in your shell) rather than
 * editing this file.
 */
define('RB_R2_ACCOUNT_ID',  getenv('RB_R2_ACCOUNT_ID')  ?: '');
define('RB_R2_ACCESS_KEY',  getenv('RB_R2_ACCESS_KEY')  ?: '');
define('RB_R2_SECRET_KEY',  getenv('RB_R2_SECRET_KEY')  ?: '');
define('RB_R2_BUCKET',      getenv('RB_R2_BUCKET')      ?: '');
// Public base URL for the bucket (R2's own r2.dev subdomain, or a custom
// domain you attached) — used to build links to public assets (property
// photos, avatars). Not needed for private assets; those are always
// streamed through a PHP gate, never linked to directly.
define('RB_R2_PUBLIC_URL',  getenv('RB_R2_PUBLIC_URL')  ?: '');
