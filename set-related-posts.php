<?php
/*
Plugin Name: Set Related Content
Description: Allows adding multiple related posts to a post with sorting and title lookup.
Version: 1.2
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Main plugin class
class Set_Related_Content {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_related_posts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_search_posts', [$this, 'search_posts']);
        add_action('wp_ajax_save_related_post', [$this, 'save_related_post']);
        add_action('wp_ajax_remove_related_post', [$this, 'remove_related_post']);
        add_action('wp_ajax_save_sorted_related_posts', [$this, 'save_sorted_related_posts']);
    }

    public function add_meta_box() {
        add_meta_box(
            'set_related_content',
            __('Related Posts', 'set-related-content'),
            [$this, 'render_meta_box'],
            'post',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('set_related_content_nonce', 'set_related_content_nonce');
        $related_posts = get_post_meta($post->ID, '_related_posts', true);
        ?>
        <div id="set-related-content">
            <input type="text" id="related-post-search" size="30" placeholder="<?php esc_attr_e('Search for posts...', 'set-related-content'); ?>">
            <ul id="related-posts-list">
                <?php
                if ($related_posts) {
                    foreach ($related_posts as $related_post_id) {
                        $related_post = get_post($related_post_id);
                        if ($related_post) {
                            echo '<li data-id="' . esc_attr($related_post_id) . '">' . esc_html($related_post->post_title) . ' <button style="font-size:0.8em;" class="remove" type="button">' . __('Remove', 'set-related-content') . '</button></li>';
                        }
                    }
                }
                ?>
            </ul>
        </div>
        <?php
    }

    public function save_related_posts($post_id) {
        if (!$this->verify_nonce()) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $related_posts = isset($_POST['related_posts']) ? array_map('intval', $_POST['related_posts']) : [];
        update_post_meta($post_id, '_related_posts', $related_posts);
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('set-related-content-admin', plugin_dir_url(__FILE__) . 'js/set-related-content-admin.js', ['jquery', 'jquery-ui-sortable'], '1.2', true);
        wp_localize_script('set-related-content-admin', 'setRelatedContent', ['ajaxurl' => admin_url('admin-ajax.php')]);
    }

    // Nonce verification
    private function verify_nonce() {
        return isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'set_related_content_nonce');
    }

    // AJAX handler for post search
    public function search_posts() {
        $search = sanitize_text_field($_GET['search']);
        $args = [
            's' => $search,
            'post_type' => 'post',
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

    // AJAX handler to save the related post immediately
    public function save_related_post() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $post_id = intval($_POST['post_id']);
        $post_parent_id = intval($_POST['post_parent_id']);
        $related_posts = get_post_meta($post_parent_id, '_related_posts', true) ?: [];

        if (!in_array($post_id, $related_posts, true)) {
            $related_posts[] = $post_id;
            update_post_meta($post_parent_id, '_related_posts', $related_posts);
            wp_send_json_success(__('Related post added.', 'set-related-content'));
        } else {
            wp_send_json_error(__('Related post already exists.', 'set-related-content'));
        }
    }

    // AJAX handler to remove the related post immediately
    public function remove_related_post() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $post_id = intval($_POST['post_id']);
        $post_parent_id = intval($_POST['post_parent_id']);
        $related_posts = get_post_meta($post_parent_id, '_related_posts', true);

        if ($related_posts && in_array($post_id, $related_posts, true)) {
            $related_posts = array_diff($related_posts, [$post_id]);
            update_post_meta($post_parent_id, '_related_posts', $related_posts);
            wp_send_json_success(__('Related post removed.', 'set-related-content'));
        } else {
            wp_send_json_error(__('Related post not found.', 'set-related-content'));
        }
    }

    // AJAX handler to save the sorted order of related posts
    public function save_sorted_related_posts() {
        if (!$this->verify_nonce()) {
            wp_send_json_error(__('Nonce verification failed.', 'set-related-content'));
            return;
        }

        $post_parent_id = intval($_POST['post_parent_id']);
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];

        update_post_meta($post_parent_id, '_related_posts', $post_ids);
        wp_send_json_success(__('Sorted related posts saved.', 'set-related-content'));
    }
}

// Initialize the plugin
new Set_Related_Content();
