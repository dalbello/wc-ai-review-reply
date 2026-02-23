<?php
/**
 * Plugin Name: WC AI Review Reply
 * Plugin URI: https://tinyship.ai/plugins/
 * Description: Generate AI reply drafts for WooCommerce product reviews with one click.
 * Version: 1.0.0
 * Author: TinyShip
 * Author URI: https://tinyship.ai
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wc-ai-review-reply
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WCAIReviewReply {
    const OPTION_KEY = 'wc_ai_review_reply_settings';
    const NONCE_ACTION = 'wc_ai_review_reply_nonce';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('comment_text', [$this, 'inject_generate_button'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wc_ai_review_generate_reply', [$this, 'ajax_generate_reply']);
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('WC AI Review Reply', 'wc-ai-review-reply'),
            __('AI Review Reply', 'wc-ai-review-reply'),
            'manage_woocommerce',
            'wc-ai-review-reply',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('wc_ai_review_reply_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'api_key' => '',
                'model' => 'gpt-4o-mini',
                'tone' => 'professional',
            ],
        ]);
    }

    public function sanitize_settings($input) {
        return [
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'model' => sanitize_text_field($input['model'] ?? 'gpt-4o-mini'),
            'tone' => in_array(($input['tone'] ?? 'professional'), ['professional', 'friendly', 'apologetic'], true)
                ? $input['tone']
                : 'professional',
        ];
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $settings = get_option(self::OPTION_KEY, []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WC AI Review Reply', 'wc-ai-review-reply'); ?></h1>
            <p><?php esc_html_e('Add your OpenAI API key to generate reply drafts for WooCommerce reviews.', 'wc-ai-review-reply'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_review_reply_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wc-airr-api-key"><?php esc_html_e('OpenAI API Key', 'wc-ai-review-reply'); ?></label></th>
                        <td><input id="wc-airr-api-key" type="password" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wc-airr-model"><?php esc_html_e('Model', 'wc-ai-review-reply'); ?></label></th>
                        <td><input id="wc-airr-model" type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[model]" value="<?php echo esc_attr($settings['model'] ?? 'gpt-4o-mini'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wc-airr-tone"><?php esc_html_e('Default Tone', 'wc-ai-review-reply'); ?></label></th>
                        <td>
                            <select id="wc-airr-tone" name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]">
                                <?php foreach (['professional', 'friendly', 'apologetic'] as $tone) : ?>
                                    <option value="<?php echo esc_attr($tone); ?>" <?php selected(($settings['tone'] ?? 'professional'), $tone); ?>><?php echo esc_html(ucfirst($tone)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'edit-comments.php') {
            return;
        }

        wp_register_script('wc-ai-review-reply-admin', '', ['jquery'], '1.0.0', true);
        wp_enqueue_script('wc-ai-review-reply-admin');
        wp_localize_script('wc-ai-review-reply-admin', 'WCAIRR', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'defaultTone' => (get_option(self::OPTION_KEY, [])['tone'] ?? 'professional'),
            'labels' => [
                'generating' => __('Generating…', 'wc-ai-review-reply'),
                'error' => __('Could not generate reply. Check API key/settings.', 'wc-ai-review-reply'),
            ],
        ]);

        $inline_js = <<<'JS'
jQuery(function($){
  $(document).on('click', '.wc-airr-generate', function(e){
    e.preventDefault();
    const $btn = $(this);
    const commentId = $btn.data('comment-id');
    const tone = $btn.closest('.wc-airr-wrap').find('.wc-airr-tone').val() || WCAIRR.defaultTone;
    const $out = $('#wc-airr-output-' + commentId);

    $btn.prop('disabled', true).text(WCAIRR.labels.generating);
    $out.text('');

    $.post(WCAIRR.ajaxUrl, {
      action: 'wc_ai_review_generate_reply',
      nonce: WCAIRR.nonce,
      comment_id: commentId,
      tone: tone
    }).done(function(resp){
      if (resp && resp.success && resp.data && resp.data.reply) {
        $out.text(resp.data.reply);
      } else {
        $out.text(WCAIRR.labels.error);
      }
    }).fail(function(){
      $out.text(WCAIRR.labels.error);
    }).always(function(){
      $btn.prop('disabled', false).text('Generate Reply');
    });
  });
});
JS;
        wp_add_inline_script('wc-ai-review-reply-admin', $inline_js);
    }

    public function inject_generate_button($comment_text, $comment) {
        if (!is_admin() || !current_user_can('manage_woocommerce')) {
            return $comment_text;
        }
        if (!isset($_GET['comment_type']) || $_GET['comment_type'] !== 'review') {
            return $comment_text;
        }

        $comment_id = (int) $comment->comment_ID;
        $markup = '<div class="wc-airr-wrap" style="margin-top:8px;padding:8px;border:1px solid #ddd;background:#fff;">';
        $markup .= '<label style="margin-right:6px;">Tone</label>';
        $markup .= '<select class="wc-airr-tone"><option value="professional">Professional</option><option value="friendly">Friendly</option><option value="apologetic">Apologetic</option></select> ';
        $markup .= '<button class="button button-secondary wc-airr-generate" data-comment-id="' . esc_attr($comment_id) . '">Generate Reply</button>';
        $markup .= '<pre id="wc-airr-output-' . esc_attr($comment_id) . '" style="white-space:pre-wrap;margin-top:8px;"></pre>';
        $markup .= '</div>';

        return $comment_text . $markup;
    }

    public function ajax_generate_reply() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        $comment_id = absint($_POST['comment_id'] ?? 0);
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $review = get_comment($comment_id);

        if (!$review || $review->comment_type !== 'review') {
            wp_send_json_error(['message' => 'Review not found'], 404);
        }

        $settings = get_option(self::OPTION_KEY, []);
        $api_key = $settings['api_key'] ?? '';
        $model = $settings['model'] ?? 'gpt-4o-mini';

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Missing API key'], 400);
        }

        $rating = get_comment_meta($comment_id, 'rating', true);
        $product = get_post($review->comment_post_ID);
        $product_name = $product ? $product->post_title : 'the product';

        $system = 'You write concise, helpful customer support replies for e-commerce product reviews.';
        $user = sprintf(
            "Write a %s reply draft to this WooCommerce review.\nProduct: %s\nRating: %s/5\nReviewer: %s\nReview: %s\n\nRules:\n- Keep to 2-4 sentences\n- Be specific and human\n- Never promise refunds/replacements directly\n- If negative review, acknowledge issue and invite support follow-up",
            $tone,
            $product_name,
            $rating ? $rating : 'unknown',
            $review->comment_author,
            wp_strip_all_tags($review->comment_content)
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $reply = $body['choices'][0]['message']['content'] ?? '';

        if ($code >= 300 || empty($reply)) {
            wp_send_json_error(['message' => 'OpenAI request failed', 'debug' => $body], 500);
        }

        wp_send_json_success(['reply' => trim($reply)]);
    }
}

new WCAIReviewReply();
