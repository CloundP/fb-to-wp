<?php
/**
 * Plugin Name: Facebook Page Auto Poster (+ Auto Refresh Token + Placeholder)
 * Description: ดึงโพสต์จาก Facebook Page มาโพสต์อัตโนมัติใน WordPress พร้อมต่ออายุโทเคนอัตโนมัติ และตั้งรูป Placeholder เมื่อโพสต์ไม่มีรูป
 * Version: 1.5
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

/* =========================================================
 * Debug helpers (mask tokens)
 * ========================================================= */
function _fb_to_wp_mask_token_in_text($text) {
    if (!is_string($text)) $text = print_r($text, true);
    $text = preg_replace_callback('/(access_token=)([^&\s]+)/', function($m){
        $tok = $m[2]; $len = strlen($tok);
        if ($len <= 16) return $m[1].'***';
        return $m[1].substr($tok,0,8).'***'.substr($tok,-6);
    }, $text);
    $text = preg_replace_callback('/([Ee][A-Za-z0-9]{20,})/', function($m){
        $tok = $m[1]; $len = strlen($tok);
        if ($len <= 20) return '***';
        return substr($tok,0,8).'***'.substr($tok,-6);
    }, $text);
    return $text;
}
function _fb_to_wp_set_debug($text) {
    update_option('fb_to_wp_last_debug', _fb_to_wp_mask_token_in_text($text));
}
function _fb_to_wp_append_debug($text) {
    $old = get_option('fb_to_wp_last_debug', '');
    $new = trim($old . "\n" . _fb_to_wp_mask_token_in_text($text));
    update_option('fb_to_wp_last_debug', $new);
}

/* =========================================================
 * Activation / Cron
 * ========================================================= */
register_activation_hook(__FILE__, function(){
    if (!wp_next_scheduled('fb_to_wp_fetch_event')) {
        wp_schedule_event(time(), 'hourly', 'fb_to_wp_fetch_event');
    }
    if (!wp_next_scheduled('fb_to_wp_refresh_event')) {
        wp_schedule_event(time(), 'daily', 'fb_to_wp_refresh_event');
    }
});
register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook('fb_to_wp_fetch_event');
    wp_clear_scheduled_hook('fb_to_wp_refresh_event');
});
add_action('fb_to_wp_fetch_event', function(){ fb_to_wp_fetch_and_post(false); });
add_action('fb_to_wp_refresh_event', function(){ fb_to_wp_maybe_refresh_tokens(false); });

/* =========================================================
 * Admin Menu / Settings
 * ========================================================= */
add_action('admin_menu', function(){
    add_menu_page(
        'FB Auto Poster',
        'FB Auto Poster',
        'manage_options',
        'fb-to-wp',
        'fb_to_wp_settings_page',
        'dashicons-facebook',
        20
    );
});

function fb_to_wp_settings_page() {
    if (!current_user_can('manage_options')) return;

    // Save settings
    if (isset($_POST['fb_to_wpw_settings_submit']) && check_admin_referer('fb_to_wp_settings')) {
        update_option('fb_app_id', sanitize_text_field($_POST['fb_app_id'] ?? ''));
        update_option('fb_app_secret', sanitize_text_field($_POST['fb_app_secret'] ?? ''));
        update_option('fb_user_token', sanitize_text_field($_POST['fb_user_token'] ?? '')); // Long-lived user token
        update_option('fb_page_id', sanitize_text_field($_POST['fb_page_id'] ?? ''));
        update_option('fb_page_token', sanitize_text_field($_POST['fb_page_token'] ?? ''));

        // Placeholder options
        update_option('fb_placeholder_mode', sanitize_text_field($_POST['fb_placeholder_mode'] ?? 'generate'));
        update_option('fb_placeholder_text', sanitize_text_field($_POST['fb_placeholder_text'] ?? 'คณะครุศาสตร์'));
        update_option('fb_placeholder_font_path', sanitize_text_field($_POST['fb_placeholder_font_path'] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'));
        update_option('fb_placeholder_w', max(300, intval($_POST['fb_placeholder_w'] ?? 1200)));
        update_option('fb_placeholder_h', max(300, intval($_POST['fb_placeholder_h'] ?? 630)));
        update_option('fb_placeholder_bg', sanitize_text_field($_POST['fb_placeholder_bg'] ?? '#0ea5e9'));
        update_option('fb_placeholder_fg', sanitize_text_field($_POST['fb_placeholder_fg'] ?? '#ffffff'));
        update_option('fb_placeholder_attachment_id', intval($_POST['fb_placeholder_attachment_id'] ?? 0));

        echo '<div class="updated"><p>บันทึกการตั้งค่าแล้ว</p></div>';
    }

    // Manual fetch
    if (isset($_POST['fb_to_wp_fetch_now']) && check_admin_referer('fb_to_wp_settings')) {
        $res = fb_to_wp_fetch_and_post(true);
        if (!empty($res['ok'])) {
            printf('<div class="updated"><p>ดึงสำเร็จ: สร้าง %d โพสต์, ข้าม %d โพสต์</p></div>', $res['created'], $res['skipped']);
        } else {
            printf('<div class="error"><p>ดึงไม่สำเร็จ: %s</p></div>', esc_html($res['error'] ?? 'unknown'));
        }
    }

    // Manual refresh tokens
    if (isset($_POST['fb_to_wp_refresh_tokens']) && check_admin_referer('fb_to_wp_settings')) {
        $r = fb_to_wp_maybe_refresh_tokens(true, true);
        if (!empty($r['ok'])) {
            echo '<div class="updated"><p>ต่ออายุโทเคนสำเร็จ</p></div>';
        } else {
            printf('<div class="error"><p>ต่ออายุโทเคนไม่สำเร็จ: %s</p></div>', esc_html($r['error'] ?? 'unknown'));
        }
    }

    $app_id      = get_option('fb_app_id', '');
    $app_secret  = get_option('fb_app_secret', '');
    $user_token  = get_option('fb_user_token', '');
    $page_id     = get_option('fb_page_id', '');
    $page_token  = get_option('fb_page_token', '');
    $last_debug  = get_option('fb_to_wp_last_debug', '');

    $user_token_info = fb_to_wp_debug_token($user_token, $app_id, $app_secret);
    $page_token_info = fb_to_wp_debug_token($page_token, $app_id, $app_secret);

    ?>
    <div class="wrap">
        <h1>Facebook Auto Poster</h1>
        <form method="post">
            <?php wp_nonce_field('fb_to_wp_settings'); ?>
            <h2>โทเคน & แอป</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">App ID</th>
                    <td><input type="text" name="fb_app_id" value="<?php echo esc_attr($app_id); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">App Secret</th>
                    <td><input type="text" name="fb_app_secret" value="<?php echo esc_attr($app_secret); ?>" class="regular-text" autocomplete="off"></td>
                </tr>
                <tr>
                    <th scope="row">Long-lived User Token</th>
                    <td>
                        <textarea name="fb_user_token" rows="3" class="large-text" autocomplete="off"><?php echo esc_textarea($user_token); ?></textarea>
                        <p class="description">ต้องเป็น Long-lived (~60 วัน). สิทธิ์อย่างน้อย: pages_show_list, pages_read_engagement (แนะนำ pages_manage_posts)</p>
                        <?php echo fb_to_wp_render_token_status($user_token_info); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Page ID</th>
                    <td><input type="text" name="fb_page_id" value="<?php echo esc_attr($page_id); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row">Page Access Token</th>
                    <td>
                        <textarea name="fb_page_token" rows="3" class="large-text" autocomplete="off"><?php echo esc_textarea($page_token); ?></textarea>
                        <p class="description">ปลั๊กอินจะอัปเดตให้อัตโนมัติเมื่อ Refresh สำเร็จ</p>
                        <?php echo fb_to_wp_render_token_status($page_token_info); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Placeholder เมื่อไม่มีรูป</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="fb_placeholder_mode" value="generate" <?php checked(get_option('fb_placeholder_mode','generate'),'generate'); ?>>
                                สร้างรูปพร้อมข้อความ (แนะนำ)
                            </label><br>
                            <label>
                                <input type="radio" name="fb_placeholder_mode" value="upload" <?php checked(get_option('fb_placeholder_mode','generate'),'upload'); ?>>
                                ใช้รูปจาก Media Library (ระบุ Attachment ID)
                            </label>
                        </fieldset>

                        <p><strong>ข้อความบนรูป</strong> (โหมดสร้าง):<br>
                            <input type="text" name="fb_placeholder_text" value="<?php echo esc_attr(get_option('fb_placeholder_text','คณะครุศาสตร์')); ?>" class="regular-text">
                        </p>

                        <p><strong>ฟอนต์ .ttf</strong> (พาธไฟล์บนเซิร์ฟเวอร์หรือ URL):<br>
                            <input type="text" name="fb_placeholder_font_path" value="<?php echo esc_attr(get_option('fb_placeholder_font_path','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')); ?>" class="large-text">
                            <br><span class="description">แนะนำ DejaVuSans.ttf (รองรับภาษาไทย)</span>
                        </p>

                        <p><strong>ขนาดรูป</strong> (พิกเซล):<br>
                            กว้าง <input type="number" name="fb_placeholder_w" value="<?php echo esc_attr(get_option('fb_placeholder_w',1200)); ?>" min="300" step="10" style="width:100px;">
                            × สูง <input type="number" name="fb_placeholder_h" value="<?php echo esc_attr(get_option('fb_placeholder_h',630)); ?>" min="300" step="10" style="width:100px;">
                        </p>

                        <p><strong>สีพื้นหลัง</strong> (hex):
                            <input type="text" name="fb_placeholder_bg" value="<?php echo esc_attr(get_option('fb_placeholder_bg','#0ea5e9')); ?>" style="width:120px;" placeholder="#0ea5e9">
                            &nbsp; <strong>สีตัวอักษร</strong>:
                            <input type="text" name="fb_placeholder_fg" value="<?php echo esc_attr(get_option('fb_placeholder_fg','#ffffff')); ?>" style="width:120px;" placeholder="#ffffff">
                        </p>

                        <p><strong>Media Attachment ID</strong> (โหมดใช้รูปอัปโหลด):
                            <input type="number" name="fb_placeholder_attachment_id" value="<?php echo esc_attr(get_option('fb_placeholder_attachment_id','')); ?>" style="width:160px;">
                            <br><span class="description">อัปโหลดรูปใน Media Library แล้วจดเลข ID มากรอก</span>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button class="button button-primary" type="submit" name="fb_to_wpw_settings_submit">บันทึกการตั้งค่า</button>
                <button class="button" type="submit" name="fb_to_wp_fetch_now">🚀 Fetch Now</button>
                <button class="button" type="submit" name="fb_to_wp_refresh_tokens">♻️ Refresh Tokens Now</button>
            </p>
        </form>

        <h2>Debug ล่าสุด</h2>
        <pre style="background:#fff;border:1px solid #ddd;padding:12px;max-height:340px;overflow:auto;"><?php echo esc_html($last_debug ?: '—'); ?></pre>
    </div>
    <?php
}

/* =========================================================
 * Token helpers
 * ========================================================= */
function fb_to_wp_debug_token($token, $app_id, $app_secret) {
    if (!$token || !$app_id || !$app_secret) return null;
    $app_token = $app_id . '|' . $app_secret;
    $url = add_query_arg([
        'input_token'  => $token,
        'access_token' => $app_token
    ], 'https://graph.facebook.com/v20.0/debug_token');

    $res = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($res)) return ['error' => $res->get_error_message()];
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if ($code !== 200) return ['error' => $body['error']['message'] ?? ('HTTP '.$code)];
    return $body['data'] ?? null;
}

function fb_to_wp_render_token_status($info) {
    if (!$info) return '<p><em>ยังไม่มีข้อมูลโทเคน</em></p>';
    if (!empty($info['error'])) return '<p style="color:#c00;"><strong>Error:</strong> '.esc_html($info['error']).'</p>';
    $expires = isset($info['expires_at']) ? date_i18n('Y-m-d H:i:s', intval($info['expires_at'])) : '—';
    $scopes  = !empty($info['scopes']) ? implode(', ', array_map('esc_html', $info['scopes'])) : '—';
    $is_valid = !empty($info['is_valid']) ? 'valid ✅' : 'invalid ❌';
    $out  = '<ul style="margin:0;">';
    $out .= '<li>Status: '.$is_valid.'</li>';
    $out .= '<li>Expires at: '.$expires.'</li>';
    $out .= '<li>Scopes: '.$scopes.'</li>';
    $out .= '</ul>';
    return $out;
}

/**
 * Refresh long-lived user token & page token when expiring (<=15 days) or on demand.
 * @param bool $manual  เพิ่ม log
 * @param bool $force   บังคับต่ออายุทันที
 * @return array ['ok'=>bool, 'error'=>string|null]
 */
function fb_to_wp_maybe_refresh_tokens($manual = false, $force = false) {
    $app_id     = trim(get_option('fb_app_id',''));
    $app_secret = trim(get_option('fb_app_secret',''));
    $user_token = trim(get_option('fb_user_token',''));
    $page_id    = trim(get_option('fb_page_id',''));

    $out = ['ok'=>false, 'error'=>null];

    if (!$app_id || !$app_secret || !$user_token) {
        $out['error'] = 'ต้องกรอก App ID, App Secret และ Long-lived User Token ก่อน';
        if ($manual) _fb_to_wp_append_debug($out['error']);
        return $out;
    }

    $info = fb_to_wp_debug_token($user_token, $app_id, $app_secret);
    $need_refresh = true;
    if (is_array($info) && empty($info['error']) && !empty($info['is_valid'])) {
        $expires_at = intval($info['expires_at'] ?? 0);
        if ($expires_at > 0) {
            $days_left = floor(($expires_at - time()) / 86400);
            $need_refresh = ($days_left <= 15);
            if ($manual) _fb_to_wp_append_debug("User token days left: ".$days_left);
        }
    }

    if (!$need_refresh && !$force) {
        $out['ok'] = true; // ยังไม่ต้องต่อ
        return $out;
    }

    // Exchange user token → long-lived (server-side)
    $url = add_query_arg([
        'grant_type'        => 'fb_exchange_token',
        'client_id'         => $app_id,
        'client_secret'     => $app_secret,
        'fb_exchange_token' => $user_token,
    ], 'https://graph.facebook.com/v20.0/oauth/access_token');

    $res = wp_remote_get($url, ['timeout'=>20]);
    if (is_wp_error($res)) {
        $out['error'] = 'wp_remote_get error (exchange): '.$res->get_error_message();
        _fb_to_wp_append_debug($out['error']);
        return $out;
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    _fb_to_wp_append_debug("Exchange response HTTP $code ".print_r($body,true));

    if ($code !== 200 || empty($body['access_token'])) {
        $out['error'] = $body['error']['message'] ?? ('HTTP '.$code.' (exchange failed)');
        return $out;
    }

    $new_user_token = $body['access_token'];
    update_option('fb_user_token', $new_user_token);

    // Pull page token from /me/accounts
    if ($page_id) {
        $url2 = add_query_arg([
            'fields'       => 'id,name,access_token',
            'access_token' => $new_user_token
        ], 'https://graph.facebook.com/v20.0/me/accounts');

        $res2 = wp_remote_get($url2, ['timeout'=>20]);
        if (is_wp_error($res2)) {
            $out['error'] = 'wp_remote_get error (me/accounts): '.$res2->get_error_message();
            _fb_to_wp_append_debug($out['error']);
            return $out;
        }
        $code2 = wp_remote_retrieve_response_code($res2);
        $body2 = json_decode(wp_remote_retrieve_body($res2), true);
        _fb_to_wp_append_debug("me/accounts HTTP $code2 ".print_r($body2,true));

        if ($code2 !== 200 || empty($body2['data'])) {
            $out['error'] = $body2['error']['message'] ?? ('HTTP '.$code2.' (me/accounts failed)');
            return $out;
        }

        $found = null;
        foreach ($body2['data'] as $pg) {
            if (!empty($pg['id']) && $pg['id'] == $page_id) { $found = $pg; break; }
        }
        if (!$found || empty($found['access_token'])) {
            $out['error'] = 'ไม่พบ Page Token ของเพจที่ระบุใน /me/accounts';
            return $out;
        }
        update_option('fb_page_token', $found['access_token']);
    }

    $out['ok'] = true;
    return $out;
}

/* =========================================================
 * Fetch posts → WP posts
 * ========================================================= */
function fb_to_wp_fetch_and_post($manual = false) {
    $page_id    = trim(get_option('fb_page_id', ''));
    $page_token = trim(get_option('fb_page_token', ''));
    $out = ['ok'=>false, 'created'=>0, 'skipped'=>0, 'error'=>null];

    if (!$page_id || !$page_token) {
        $out['error'] = 'ยังไม่ได้ตั้งค่า Page ID หรือ Page Token';
        _fb_to_wp_set_debug("CONFIG ERROR: missing page_id or page_token");
        return $out;
    }

    $limit = 5;
    $url = add_query_arg([
        'fields'       => 'id,message,created_time,permalink_url,full_picture,attachments{url,title,description,type}',
        'limit'        => $limit,
        'access_token' => $page_token,
    ], "https://graph.facebook.com/v20.0/{$page_id}/posts");

    $response = wp_remote_get($url, ['timeout'=>20]);
    if (is_wp_error($response)) {
        $out['error'] = 'wp_remote_get error: '.$response->get_error_message();
        _fb_to_wp_set_debug($out['error']);
        return $out;
    }

    $code    = wp_remote_retrieve_response_code($response);
    $body    = wp_remote_retrieve_body($response);
    $decoded = json_decode($body);

    _fb_to_wp_set_debug("HTTP $code\nURL: "._fb_to_wp_mask_token_in_text($url)."\nRAW: "._fb_to_wp_mask_token_in_text($body)."\n");

    if ($code !== 200) {
        $msg = 'HTTP '.$code;
        if (isset($decoded->error->message)) $msg .= ' - '.$decoded->error->message;
        $out['error'] = $msg;
        return $out;
    }
    if (isset($decoded->error)) {
        $out['error'] = 'Graph error: '.($decoded->error->message ?? 'unknown');
        return $out;
    }
    if (empty($decoded->data)) {
        $out['ok'] = true; // ไม่มีโพสต์ใหม่
        return $out;
    }

    foreach ($decoded->data as $p) {
        $fb_id = $p->id ?? '';
        if (!$fb_id) { $out['skipped']++; continue; }

        // กันโพสต์ซ้ำ
        $existing = get_posts([
            'meta_key'    => 'fb_post_id',
            'meta_value'  => $fb_id,
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 1
        ]);
        if ($existing) { $out['skipped']++; continue; }

        // เนื้อหาโพสต์
        $content = '';
        if (!empty($p->message)) {
            $content .= wp_kses($p->message, [
                'a'=>['href'=>[], 'title'=>[], 'target'=>[], 'rel'=>[]],
                'br'=>[], 'strong'=>[], 'b'=>[],
            ]);
        }
        if (!empty($p->permalink_url)) {
            $content .= "<br><a href='".esc_url($p->permalink_url)."' target='_blank' rel='noopener'>ดูโพสต์ต้นฉบับ</a>";
        }
        if (!empty($p->attachments->data)) {
            foreach ($p->attachments->data as $attach) {
                if (($attach->type ?? '') === 'share') {
                    $shared_url   = esc_url($attach->url ?? '');
                    $shared_title = esc_html($attach->title ?? '');
                    $shared_desc  = esc_html($attach->description ?? '');
                    $content     .= "<hr><strong>แชร์จาก:</strong> <a href='{$shared_url}' target='_blank' rel='noopener'>{$shared_title}</a><br>{$shared_desc}";
                }
            }
        }

        $title_source = $p->message ?? 'Facebook Post';
        $post_id = wp_insert_post([
            'post_title'   => wp_strip_all_tags(wp_trim_words($title_source, 10, '...')),
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'post'
        ], true);

        if (is_wp_error($post_id)) {
            _fb_to_wp_append_debug("wp_insert_post error: ".$post_id->get_error_message());
            $out['skipped']++;
            continue;
        }

        // รูปภาพ: FB → Featured, otherwise Placeholder
        if (!empty($p->full_picture)) {
            fb_to_wp_set_featured_image($p->full_picture, $post_id);
        } else {
            fb_to_wp_set_placeholder_image($post_id);
        }

        update_post_meta($post_id, 'fb_post_id', $fb_id);
        $out['created']++;
    }

    $out['ok'] = true;
    if ($manual) {
        error_log("FB Plugin: Manual fetch run at ".date('Y-m-d H:i:s')." | created={$out['created']} skipped={$out['skipped']}");
    }
    return $out;
}

/* =========================================================
 * Media helpers
 * ========================================================= */
function fb_to_wp_set_featured_image($image_url, $post_id) {
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        _fb_to_wp_append_debug('download_url error: '.$tmp->get_error_message());
        return false;
    }
    $filename = basename(parse_url($image_url, PHP_URL_PATH) ?: 'fb-image.jpg');

    $file_array = ['name'=>$filename, 'tmp_name'=>$tmp];
    $sideload = wp_handle_sideload($file_array, ['test_form'=>false]);
    if (!empty($sideload['error'])) {
        @unlink($tmp);
        _fb_to_wp_append_debug('wp_handle_sideload error: '.$sideload['error']);
        return false;
    }

    $attachment = [
        'post_mime_type' => $sideload['type'],
        'post_title'     => sanitize_file_name(pathinfo($sideload['file'], PATHINFO_FILENAME)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $sideload['file'], $post_id);
    if (is_wp_error($attach_id)) {
        _fb_to_wp_append_debug('wp_insert_attachment error: '.$attach_id->get_error_message());
        return false;
    }
    require_once ABSPATH.'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $sideload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    set_post_thumbnail($post_id, $attach_id);
    return true;
}

/* =========================================================
 * Placeholder (generate or use uploaded)
 * ========================================================= */
function fb_to_wp_hex2rgb($hex) {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat(substr($hex,0,1),2));
        $g = hexdec(str_repeat(substr($hex,1,1),2));
        $b = hexdec(str_repeat(substr($hex,2,1),2));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return [$r,$g,$b];
}

function fb_to_wp_set_placeholder_image($post_id) {
    $mode      = get_option('fb_placeholder_mode','generate');
    $text      = get_option('fb_placeholder_text','คณะครุศาสตร์');
    $font_path = get_option('fb_placeholder_font_path','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');
    $w         = max(300, intval(get_option('fb_placeholder_w',1200)));
    $h         = max(300, intval(get_option('fb_placeholder_h',630)));
    $bg_hex    = get_option('fb_placeholder_bg','#0ea5e9');
    $fg_hex    = get_option('fb_placeholder_fg','#ffffff');

    if ($mode === 'upload') {
        $attach_id = intval(get_option('fb_placeholder_attachment_id',0));
        if ($attach_id > 0) {
            set_post_thumbnail($post_id, $attach_id);
            return true;
        }
        // ถ้าไม่มี ID → fallback generate
    }

    if (!function_exists('imagecreatetruecolor')) {
        _fb_to_wp_append_debug('GD library not available: cannot generate placeholder');
        return false;
    }

    $im = imagecreatetruecolor($w, $h);
    if (!$im) return false;

    [$br,$bg,$bb] = fb_to_wp_hex2rgb($bg_hex);
    [$fr,$fgc,$fb] = fb_to_wp_hex2rgb($fg_hex);
    $bg_col = imagecolorallocate($im, $br,$bg,$bb);
    $fg_col = imagecolorallocate($im, $fr,$fgc,$fb);
    imagefilledrectangle($im, 0,0, $w,$h, $bg_col);

    $font_ok = $font_path && ( file_exists($font_path) || filter_var($font_path, FILTER_VALIDATE_URL) );
    $tmp_font = null;
    if ($font_ok && filter_var($font_path, FILTER_VALIDATE_URL)) {
        $tmp_font = download_url($font_path);
        if (!is_wp_error($tmp_font) && file_exists($tmp_font)) {
            $font_path = $tmp_font;
        } else {
            $font_ok = false;
            _fb_to_wp_append_debug('Download font failed, fallback to built-in font.');
        }
    }

    if ($font_ok && function_exists('imagettfbbox') && function_exists('imagettftext')) {
        $font_size = max(18, intval(min($w,$h) * 0.11));
        $bbox = imagettfbbox($font_size, 0, $font_path, $text);
        $text_w = abs($bbox[2] - $bbox[0]);
        $text_h = abs($bbox[7] - $bbox[1]);
        $x = intval(($w - $text_w) / 2);
        $y = intval(($h + $text_h) / 2);
        $shadow = imagecolorallocatealpha($im, 0,0,0, 60);
        if ($shadow !== false) imagettftext($im, $font_size, 0, $x+2, $y+2, $shadow, $font_path, $text);
        imagettftext($im, $font_size, 0, $x, $y, $fg_col, $font_path, $text);
    } else {
        $msg = $text;
        $font = 5;
        $text_w = imagefontwidth($font) * strlen($msg);
        $text_h = imagefontheight($font);
        $x = intval(($w - $text_w) / 2);
        $y = intval(($h - $text_h) / 2);
        imagestring($im, $font, $x, $y, $msg, $fg_col);
        _fb_to_wp_append_debug('Using built-in bitmap font (TTF not found). Thai may not render correctly.');
    }

    $tmp = wp_tempnam('fb-placeholder.png');
    if (!$tmp) { imagedestroy($im); return false; }
    imagepng($im, $tmp);
    imagedestroy($im);

    $file_array = ['name' => 'fb-placeholder.png', 'tmp_name' => $tmp];
    $sideload = wp_handle_sideload($file_array, ['test_form'=>false]);
    if (!empty($sideload['error'])) {
        @unlink($tmp);
        _fb_to_wp_append_debug('wp_handle_sideload error (placeholder): '.$sideload['error']);
        return false;
    }

    $attachment = [
        'post_mime_type' => $sideload['type'],
        'post_title'     => 'fb-placeholder',
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];
    $attach_id = wp_insert_attachment($attachment, $sideload['file'], $post_id);
    if (is_wp_error($attach_id)) {
        _fb_to_wp_append_debug('wp_insert_attachment error (placeholder): '.$attach_id->get_error_message());
        return false;
    }

    require_once ABSPATH.'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $sideload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    set_post_thumbnail($post_id, $attach_id);

    if ($tmp_font && file_exists($tmp_font)) @unlink($tmp_font);
    return true;
}
