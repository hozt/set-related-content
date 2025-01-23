<?php
/*
Plugin Name: Set Related Content
Description: Allows adding multiple related posts and events to posts and podcast episodes with sorting and title lookup.
Version: 1.6.1
Author: Jeff Haug
Author URI: https://hozt.com
Text Domain: set-related-content
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;

add_action('graphql_register_types', function () {
    $post_types = ['post', 'podcastEpisode'];
    foreach ($post_types as $post_type) {
        register_graphql_field($post_type, 'relatedPosts', [
            'type' => ['list_of' => 'Post'],
            'description' => 'Related posts',
            'resolve' => function ($post, $args, $context, $info) {
                $related_posts = get_post_meta($post->ID, '_related_posts', true);
                if (!$related_posts) {
                    return [];
                }
                $related_posts = array_map(function ($id) use ($context) {
                    return \WPGraphQL\Data\DataSource::resolve_post_object($id, $context);
                }, $related_posts);
                return $related_posts;
            }
        ]);
        register_graphql_field($post_type, 'relatedEvents', [
            'type' => ['list_of' => 'Event'],
            'description' => 'Related events',
            'resolve' => function ($post, $args, $context, $info) {
                $related_events = get_post_meta($post->ID, '_related_events', true);
                if (!$related_events) {
                    return [];
                }
                $related_events = array_map(function ($id) use ($context) {
                    return \WPGraphQL\Data\DataSource::resolve_post_object($id, $context);
                }, $related_events);
                return $related_events;
            }
        ]);
    }
});

class Set_Related_Content
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_related_content']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_search_content', [$this, 'search_content']);
        add_action('wp_ajax_save_related_content', [$this, 'save_related_content_ajax']);
        add_action('wp_ajax_remove_related_content', [$this, 'remove_related_content']);
        add_action('wp_ajax_save_sorted_related_content', [$this, 'save_sorted_related_content']);
        add_action('admin_footer', [$this, 'collapse_meta_boxes']);
    }

    public function add_meta_boxes()
    {
        $post_types = ['post', 'podcast_episode'];
        foreach ($post_types as $post_type) {
            add_meta_box(
                'set_related_posts',
                __('Related Posts', 'set-related-content'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default',
                ['content_type' => 'post']
            );
            add_meta_box(
                'set_related_events',
                __('Related Events', 'set-related-content'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default',
                ['content_type' => 'event']
            );
        }
    }

    public function render_meta_box($post, $metabox)
    {
        $content_type = $metabox['args']['content_type'];
        wp_nonce_field('set_related_content_nonce', 'set_related_content_nonce');
        $related_content = get_post_meta($post->ID, "_related_{$content_type}s", true);
        $has_content = !empty($related_content);
        ?>
        <div id="set-related-<?php echo esc_attr($content_type); ?>s" class="set-related-content" data-has-content="<?php echo $has_content ? 'true' : 'false'; ?>">
            <input type="hidden" name="related_<?php echo esc_attr($content_type); ?>s" id="related-<?php echo esc_attr($content_type); ?>s-input" value="<?php echo esc_attr(implode(',', (array)$related_content)); ?>">
            <input type="text" id="related-<?php echo esc_attr($content_type); ?>-search" size="30" placeholder="<?php esc_attr_e("Search for {$content_type}s...", 'set-related-content'); ?>">
            <ul id="related-<?php echo esc_attr($content_type); ?>s-list" class="related-content-list">
                <?php
                if ($related_content) {
                    foreach ($related_content as $related_id) {
                        $related_item = get_post($related_id);
                        if ($related_item) {
                            echo '<li data-id="' . esc_attr($related_id) . '">' . esc_html($related_item->post_title) . ' <button style="font-size:0.8em;" class="remove" type="button">' . __('Remove', 'set-related-content') . '</button></li>';
                        }
                    }
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function collapse_meta_boxes()
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.postbox#set_related_posts, .postbox#set_related_events').each(function() {
                var $metabox = $(this);
                var $content = $metabox.find('.set-related-content');
                var hasContent = $content.data('has-content');

                if (!hasContent) {
                    $metabox.addClass('closed');
                }
            });
        });
        </script>
        <?php
    }

    public function enqueue_admin_scripts($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('set-related-content-admin', plugin_dir_url(__FILE__) . 'js/set-related-content-admin.js', ['jquery', 'jquery-ui-sortable'], '1.3', true);
        wp_localize_script('set-related-content-admin', 'setRelatedContent', ['ajaxurl' => admin_url('admin-ajax.php')]);
    }

    public function search_content()
    {
        $search = sanitize_text_field($_GET['search']);
        $content_type = sanitize_text_field($_GET['content_type']);
        $args = [
            's' => $search,
            'post_type' => $content_type,
            'post_status' => 'publish',
            'posts_per_page' => 10,
        ];
        $query = new WP_Query($args);
        $results = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                ];
            }
        }
        wp_reset_postdata();
        wp_send_json($results);
    }

    public function save_related_content_ajax()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set_related_content_nonce')) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $content_id = intval($_POST['content_id']);
        $post_parent_id = intval($_POST['post_parent_id']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $meta_key = "_related_{$content_type}s";
        $related_content = get_post_meta($post_parent_id, $meta_key, true) ?: [];

        if (!in_array($content_id, $related_content, true)) {
            $related_content[] = $content_id;
            update_post_meta($post_parent_id, $meta_key, $related_content);
            wp_send_json_success(__('Related content added.', 'set-related-content'));
        } else {
            wp_send_json_error(__('Related content already exists.', 'set-related-content'));
        }
    }

    public function save_related_content($post_id)
    {
        if (!isset($_POST['set_related_content_nonce']) || !wp_verify_nonce($_POST['set_related_content_nonce'], 'set_related_content_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Handle related posts
        if (isset($_POST['related_posts'])) {
            $related_posts = json_decode(stripslashes($_POST['related_posts']), true);
            if (is_array($related_posts)) {
                $related_posts = array_map('intval', $related_posts);
                update_post_meta($post_id, '_related_posts', $related_posts);
            }
        }

        // Handle related events
        if (isset($_POST['related_events'])) {
            $related_events = json_decode(stripslashes($_POST['related_events']), true);
            if (is_array($related_events)) {
                $related_events = array_map('intval', $related_events);
                update_post_meta($post_id, '_related_events', $related_events);
            }
        }
    }


    public function remove_related_content()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set_related_content_nonce')) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $content_id = intval($_POST['content_id']);
        $post_parent_id = intval($_POST['post_parent_id']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $meta_key = "_related_{$content_type}s";
        $related_content = get_post_meta($post_parent_id, $meta_key, true);

        if ($related_content && in_array($content_id, $related_content, true)) {
            $related_content = array_diff($related_content, [$content_id]);
            update_post_meta($post_parent_id, $meta_key, $related_content);
            wp_send_json_success(__('Related content removed.', 'set-related-content'));
        } else {
            wp_send_json_error(__('Related content not found.', 'set-related-content'));
        }
    }

    public function save_sorted_related_content()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'set_related_content_nonce')) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $post_parent_id = intval($_POST['post_parent_id']);
        $content_ids = isset($_POST['content_ids']) ? array_map('intval', $_POST['content_ids']) : [];
        $content_type = sanitize_text_field($_POST['content_type']);
        $valid_types = ['post', 'event'];
        if (!in_array($content_type, $valid_types)) {
            $content_type = 'post'; // Default to 'post' if invalid
        }
        $meta_key = "_related_{$content_type}s";

        update_post_meta($post_parent_id, $meta_key, $content_ids);
        wp_send_json_success(__('Sorted related content saved.', 'set-related-content'));
    }

    private function verify_nonce()
    {
        return isset($_POST['set_related_content_nonce']) && wp_verify_nonce($_POST['set_related_content_nonce'], 'set_related_content_nonce');
    }
}

// Initialize the plugin
new Set_Related_Content();
