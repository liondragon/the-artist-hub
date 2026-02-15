<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend quote view tracking (customer-only views).
 */
final class TAH_Quote_View_Tracking
{
    private const POST_TYPE = 'quotes';
    private const META_VIEW_COUNT = '_tah_quote_view_count';
    private const META_LAST_VIEWED = '_tah_quote_last_viewed_at';

    /**
     * Prevent duplicate tracking within the same request.
     *
     * @var array<int, bool>
     */
    private $tracked = [];

    public function track_quote_view(int $post_id): void
    {
        if ($post_id <= 0 || isset($this->tracked[$post_id])) {
            return;
        }

        if (is_user_logged_in() || isset($_GET['nt'])) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }

        $view_count = (int) get_post_meta($post_id, self::META_VIEW_COUNT, true);
        update_post_meta($post_id, self::META_VIEW_COUNT, $view_count + 1);
        update_post_meta($post_id, self::META_LAST_VIEWED, current_time('mysql'));

        $this->tracked[$post_id] = true;
    }
}

$GLOBALS['tah_quote_view_tracking'] = new TAH_Quote_View_Tracking();

if (!function_exists('tah_track_quote_view')) {
    function tah_track_quote_view($post_id = 0): void
    {
        if (!isset($GLOBALS['tah_quote_view_tracking']) || !$GLOBALS['tah_quote_view_tracking'] instanceof TAH_Quote_View_Tracking) {
            return;
        }

        $target_post_id = (int) $post_id;
        if ($target_post_id <= 0) {
            $target_post_id = (int) get_queried_object_id();
        }
        if ($target_post_id <= 0) {
            return;
        }

        $GLOBALS['tah_quote_view_tracking']->track_quote_view($target_post_id);
    }
}
