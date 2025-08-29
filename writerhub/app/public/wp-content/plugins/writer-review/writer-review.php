<?php
/**
 * Plugin Name: WriterHub Reviews
 * Description: Lightweight reviews plugin for external books (Google Books volumeId). Use [book_reviews book_id="..."] on the book details page.
 * Version: 1.0.0
 * Author: Raya Anjum
 * License: GPL2+
 */

if (!defined('ABSPATH')) { exit; }

class WriterHub_Reviews {
    const CPT = 'book_review';
    const NONCE_ACTION = 'wh_submit_review';
    private static $instance = null;
    private static $printed_assets = false;

    public static function instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_shortcode('book_reviews', [$this, 'shortcode']);
        add_action('wp_ajax_wh_submit_review', [$this, 'ajax_submit_review']);
        add_action('wp_ajax_nopriv_wh_submit_review', [$this, 'ajax_submit_review']);
        add_filter('manage_edit-'.self::CPT.'_columns', [$this, 'admin_cols']);
        add_action('manage_'.self::CPT.'_posts_custom_column', [$this, 'admin_col_content'], 10, 2);
        register_activation_hook(__FILE__, [$this, 'on_activate']);
    }

    /** Register Reviews CPT */
    public function register_cpt() {
        $labels = [
            'name'               => 'Reviews',
            'singular_name'      => 'Review',
            'menu_name'          => 'Reviews',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Review',
            'edit_item'          => 'Edit Review',
            'new_item'           => 'New Review',
            'view_item'          => 'View Review',
            'search_items'       => 'Search Reviews',
            'not_found'          => 'No reviews found',
            'not_found_in_trash' => 'No reviews found in Trash',
        ];
        register_post_type(self::CPT, [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['editor', 'author'],
            'menu_icon' => 'dashicons-star-filled',
        ]);
    }

    /** Activation: ensure CPT exists, flush rules */
    public function on_activate() {
        $this->register_cpt();
        flush_rewrite_rules();
    }

    /** Shortcode: [book_reviews book_id="..."] */
    public function shortcode($atts) {
        $atts = shortcode_atts(['book_id' => ''], $atts);
        $book_id = sanitize_text_field($atts['book_id']);
        if (!$book_id) {
            return '<div class="wh-reviews"><em>Missing book_id.</em></div>';
        }

        // Query reviews for this book_id
        $reviews = new WP_Query([
            'post_type'      => self::CPT,
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'meta_query'     => [[
                'key'   => '_book_id',
                'value' => $book_id,
            ]],
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        // Average rating
        $sum = 0; $count = 0;
        $items_html = '';
        if ($reviews->have_posts()) {
            while ($reviews->have_posts()) {
                $reviews->the_post();
                $rating = (int) get_post_meta(get_the_ID(), '_rating', true);
                $sum += max(0, $rating); $count++;
                $items_html .= $this->render_review_item(get_post());
            }
            wp_reset_postdata();
        } else {
            $items_html = '<div class="wh-empty">No reviews yet. Be the first!</div>';
        }
        $avg = $count ? round($sum / $count, 1) : 0;

        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $login_required = apply_filters('wh_reviews_require_login', true);

        $html = '';
        $html .= '<div class="wh-reviews" data-book-id="'.esc_attr($book_id).'">';

        $html .= '<div class="wh-reviews-header">';
        $html .= '<h3>Reviews</h3>';
        $html .= '<div class="wh-avg">'. $this->render_stars($avg) .' <span class="wh-avg-num">'.esc_html($avg).'/5</span></div>';
        $html .= '</div>';

        $html .= '<div class="wh-list">'.$items_html.'</div>';

        // Review form (toggle)
        $html .= '<button class="wh-review-toggle" type="button">✍️ Write a review</button>';
        $html .= '<form class="wh-form" style="display:none" method="post">';
        $html .= '<input type="hidden" name="action" value="wh_submit_review">';
        $html .= '<input type="hidden" name="nonce" value="'.esc_attr($nonce).'">';
        $html .= '<input type="hidden" name="book_id" value="'.esc_attr($book_id).'">';

        // Rating stars input
        $html .= '<div class="wh-field">';
        $html .= '<label class="wh-label">Your rating</label>';
        $html .= '<div class="wh-stars-input" role="radiogroup" aria-label="Rating">';
        for ($i = 5; $i >= 1; $i--) {
            $html .= '<input id="wh-rate-'.$i.'" type="radio" name="rating" value="'. $i .'">';
            $html .= '<label for="wh-rate-'.$i.'" aria-label="'.$i.' star'.($i>1?'s':'').'">★</label>';
        }
        $html .= '</div></div>';

        // Review textarea
        $html .= '<div class="wh-field">';
        $html .= '<label class="wh-label">Your review</label>';
        $html .= '<textarea name="content" rows="5" placeholder="Share your thoughts..." required></textarea>';
        $html .= '</div>';

        if ($login_required && !is_user_logged_in()) {
            $html .= '<div class="wh-note">Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to post a review.</div>';
        } else {
            $html .= '<button class="wh-submit" type="submit">Submit review</button>';
        }

        $html .= '<div class="wh-msg" aria-live="polite"></div>';
        $html .= '</form>';

        // Styles + JS (printed once)
        if (!self::$printed_assets) {
            $html .= $this->styles();
            $html .= $this->script($login_required);
            self::$printed_assets = true;
        }

        $html .= '</div>'; // .wh-reviews
        return $html;
    }

    /** Renders a single review item */
    private function render_review_item(WP_Post $post) {
        $rating = (int) get_post_meta($post->ID, '_rating', true);
        $author = $post->post_author ? get_the_author_meta('display_name', $post->post_author) : 'Guest';
        $date   = get_the_date('', $post);
        $content = wpautop( wp_kses_post($post->post_content) );

        $out  = '<div class="wh-item">';
        $out .= '<div class="wh-item-head">';
        $out .= '<div class="wh-item-stars">'.$this->render_stars($rating).'</div>';
        $out .= '<div class="wh-item-meta"><span class="wh-author">'.esc_html($author).'</span> • <span class="wh-date">'.esc_html($date).'</span></div>';
        $out .= '</div>';
        $out .= '<div class="wh-item-content">'.$content.'</div>';
        $out .= '</div>';
        return $out;
    }

    /** Star renderer (supports half values) */
    private function render_stars($rating) {
        $rating = floatval($rating);
        $full = floor($rating);
        $half = ($rating - $full) >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;

        $html = '<span class="wh-stars">';
        for ($i = 0; $i < $full; $i++)  { $html .= '<span class="wh-star wh-full">★</span>'; }
        if ($half)                      { $html .= '<span class="wh-star wh-half">★</span>'; }
        for ($i = 0; $i < $empty; $i++) { $html .= '<span class="wh-star wh-empty">☆</span>'; }
        $html .= '</span>';
        return $html;
    }

    /** Ajax: submit review */
    public function ajax_submit_review() {
        // Basic checks
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Invalid request (nonce).'], 400);
        }

        $book_id = isset($_POST['book_id']) ? sanitize_text_field($_POST['book_id']) : '';
        $rating  = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

        if (!$book_id) {
            wp_send_json_error(['message' => 'Missing book_id.'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'Please select a rating between 1 and 5.'], 400);
        }
        if (strlen(trim(wp_strip_all_tags($content))) < 10) {
            wp_send_json_error(['message' => 'Review is too short.'], 400);
        }

        $login_required = apply_filters('wh_reviews_require_login', true);
        if ($login_required && !is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to review.'], 401);
        }

        $status = apply_filters('wh_reviews_auto_publish', true) ? 'publish' : 'pending';
        $author_id = get_current_user_id();

        $title = sprintf('Review for %s by %s', $book_id, $author_id ? wp_get_current_user()->display_name : 'Guest');
        $post_id = wp_insert_post([
            'post_type'    => self::CPT,
            'post_status'  => $status,
            'post_title'   => sanitize_text_field($title),
            'post_content' => $content,
            'post_author'  => $author_id,
        ], true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Could not save review.'], 500);
        }

        update_post_meta($post_id, '_book_id', $book_id);
        update_post_meta($post_id, '_rating', $rating);

        // Recalculate average
        $avg = $this->calculate_average($book_id);

        // Render the just-saved review (only if published)
        $html = '';
        if ($status === 'publish') {
            $html = $this->render_review_item(get_post($post_id));
        }

        wp_send_json_success([
            'message' => $status === 'publish' ? 'Review published!' : 'Review submitted for moderation.',
            'html'    => $html,
            'avg'     => $avg,
        ]);
    }

    private function calculate_average($book_id) {
        $q = new WP_Query([
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [[ 'key' => '_book_id', 'value' => $book_id ]]
        ]);
        $sum = 0; $count = 0;
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $r = (int) get_post_meta(get_the_ID(), '_rating', true);
                if ($r >= 1 && $r <= 5) { $sum += $r; $count++; }
            }
            wp_reset_postdata();
        }
        return $count ? round($sum / $count, 1) : 0;
    }

    /** Admin columns */
    public function admin_cols($cols) {
        $cols_out = [];
        foreach ($cols as $k=>$v) {
            $cols_out[$k] = $v;
            if ($k === 'title') {
                $cols_out['book_id'] = 'Book ID';
                $cols_out['rating']  = 'Rating';
            }
        }
        return $cols_out;
    }
    public function admin_col_content($col, $post_id) {
        if ($col === 'book_id') {
            echo esc_html(get_post_meta($post_id, '_book_id', true));
        } elseif ($col === 'rating') {
            echo esc_html(get_post_meta($post_id, '_rating', true)) . '/5';
        }
    }

    /** Inline styles (kept minimal + modern) */
    private function styles() {
        return '<style>
.wh-reviews{max-width:900px;margin:32px auto;padding:24px;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);font-family:Segoe UI,system-ui,Arial}
.wh-reviews-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.wh-avg{display:flex;align-items:center;gap:8px;font-weight:700}
.wh-avg .wh-avg-num{color:#555}
.wh-list{display:flex;flex-direction:column;gap:16px;margin-bottom:18px}
.wh-item{padding:16px;border:1px solid #eef2f7;border-radius:12px;background:#fafbfe}
.wh-item-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.wh-item-meta{color:#666;font-size:.92rem}
.wh-item-content{color:#222;line-height:1.6}
.wh-stars .wh-full{color:#f9b233}
.wh-stars .wh-half{background:linear-gradient(90deg,#f9b233 50%,#ddd 50%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.wh-stars .wh-empty{color:#ddd}
.wh-review-toggle{display:inline-block;background:#ff9800;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:700;cursor:pointer;box-shadow:0 4px 10px rgba(255,152,0,.25)}
.wh-review-toggle:hover{background:#e68900}
.wh-form{margin-top:12px;padding:16px;border:1px dashed #dfe6f1;border-radius:12px;background:#fff}
.wh-field{margin-bottom:12px}
.wh-label{display:block;margin-bottom:6px;font-weight:600;color:#333}
.wh-stars-input{display:inline-flex;flex-direction:row-reverse;gap:4px}
.wh-stars-input input{display:none}
.wh-stars-input label{font-size:26px;cursor:pointer;color:#ddd;transition:.2s}
.wh-stars-input input:checked ~ label,
.wh-stars-input label:hover,
.wh-stars-input label:hover ~ label{color:#f9b233}
.wh-form textarea{width:100%;border:1px solid #d9e1ef;border-radius:8px;padding:10px;font-size:1rem}
.wh-submit{background:#2466e8;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(36,102,232,.25)}
.wh-submit:hover{background:#1855b4}
.wh-msg{margin-top:10px;font-weight:600}
.wh-empty{color:#666}
@media (max-width:640px){
  .wh-reviews{padding:16px;border-radius:12px}
}
</style>';
    }

    /** Inline JS (vanilla) */
    private function script($login_required) {
        $ajax = admin_url('admin-ajax.php');
        $login_required = $login_required ? 'true' : 'false';
        return '<script>
document.addEventListener("click", function(e){
  if(e.target && e.target.classList.contains("wh-review-toggle")){
    const wrap = e.target.closest(".wh-reviews");
    const form = wrap.querySelector(".wh-form");
    if(form){ form.style.display = (form.style.display==="none"||!form.style.display) ? "block" : "none"; }
  }
});

document.addEventListener("submit", async function(e){
  if(!e.target.classList.contains("wh-form")) return;
  e.preventDefault();
  const form = e.target;
  const msg  = form.querySelector(".wh-msg");
  msg.textContent = "";

  const requireLogin = '.$login_required.';
  if(requireLogin && !'.(is_user_logged_in() ? 'true' : 'false').'){
    msg.textContent = "You must be logged in to review.";
    msg.style.color = "#c00";
    return;
  }

  const data = new FormData(form);
  msg.textContent = "Submitting...";
  msg.style.color = "#555";

  try{
    const res = await fetch("'.esc_url($ajax).'", { method:"POST", body:data });
    const json = await res.json();
    if(!json || json.success !== true){
      msg.textContent = (json && json.data && json.data.message) ? json.data.message : "Something went wrong.";
      msg.style.color = "#c00";
      return;
    }
    // Success
    msg.textContent = json.data.message;
    msg.style.color = "#0a7c2f";

    // Add new review to top of list if published
    if(json.data.html){
      const wrap = form.closest(".wh-reviews");
      const list = wrap.querySelector(".wh-list");
      const temp = document.createElement("div");
      temp.innerHTML = json.data.html.trim();
      const node = temp.firstElementChild;
      if(list && node){ list.insertBefore(node, list.firstChild); }
    }

    // Update average display
    const wrap = form.closest(".wh-reviews");
    const avgEl = wrap.querySelector(".wh-avg");
    if(avgEl){
      avgEl.querySelector(".wh-avg-num").textContent = json.data.avg + "/5";
      // naive star rebuild: replace full block
      avgEl.querySelector(".wh-stars")?.remove();
      const temp = document.createElement("span");
      temp.innerHTML = "'. esc_js( $this->render_stars('__AVG__') ) .'";
      avgEl.insertBefore(temp.firstChild, avgEl.firstChild);
      avgEl.innerHTML = avgEl.innerHTML.replace(/__AVG__/g, json.data.avg);
    }

    // reset form
    form.reset();
    form.style.display = "none";
  }catch(err){
    msg.textContent = "Network error.";
    msg.style.color = "#c00";
  }
});
</script>';
    }
}

WriterHub_Reviews::instance();
