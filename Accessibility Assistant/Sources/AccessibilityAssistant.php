<?php
/**
 * BoredSkull Accessibility Assistant 1.3.1 for SMF 2.1.x
 * Copyright (c) 2026 BoredSkull; ISC License.
 * Heuristic authoring assistance, not an accessibility certification.
 */
if (!defined('SMF'))
    die('No direct access');

class BoredAccessibilityAssistant
{
    const VERSION = '1.3.1';
    const MAX_INPUT = 200000;

    public static function enabled()
    {
        global $modSettings;
        return !isset($modSettings['baa_enabled']) || !empty($modSettings['baa_enabled']);
    }

    public static function setting($key, $default = 1)
    {
        global $modSettings;
        return isset($modSettings[$key]) ? (int) $modSettings[$key] : $default;
    }

    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function hookActions(&$actions)
    {
        $actions['accessibility'] = array('AccessibilityAssistant.php', 'baa_dispatch');
    }

    /** Compatibility for installations that still have the old menu hook registered. */
    public static function hookMenu(&$buttons)
    {
        // Intentionally no top-level navigation button.
    }

    /** Member-specific options belong in SMF's own profile navigation. */
    public static function hookProfileAreas(&$profile_areas)
    {
        global $txt, $user_info;
        if (!self::enabled() || empty($user_info['id']))
            return;
        loadLanguage('AccessibilityAssistant');
        $profile_areas['edit_profile']['areas']['accessibilityassistant'] = array(
            'label' => $txt['baa_title'],
            'file' => 'AccessibilityAssistant.php',
            'function' => 'baa_profile',
            'icon' => 'features',
            'permission' => array(
                'own' => array('is_not_guest'),
                'any' => array(),
            ),
        );
    }

    /** Only show personal options while viewing your own profile. */
    public static function profile()
    {
        global $user_info, $context;
        if (empty($user_info['id']) || empty($context['user']['is_owner']) || (int) $context['id_member'] !== (int) $user_info['id'])
            fatal_lang_error('no_access', false);
        self::dispatch(true);
    }

    public static function hookAdmin(&$areas)
    {
        global $txt;
        loadLanguage('AccessibilityAssistant');
        $areas['config']['areas']['accessibilityassistant'] = array(
            'label' => $txt['baa_title'],
            'file' => 'AccessibilityAssistant.php',
            'function' => 'baa_admin',
            'icon' => 'features',
            'permission' => array('admin_forum'),
        );
    }

    public static function hookLoadTheme()
    {
        global $context, $txt, $scripturl;
        if (!self::enabled() || !empty($_REQUEST['xml']))
            return;
        loadLanguage('AccessibilityAssistant');
        loadCSSFile('accessibility_assistant.css', array('minimize' => true, 'seed' => self::VERSION), 'baa_styles');
        loadJavaScriptFile('accessibility_assistant.js', array('defer' => true, 'minimize' => false, 'seed' => self::VERSION), 'baa_script');
        $data = array(
            'composerEnabled' => (bool) self::setting('baa_composer', 1),
            'skipEnabled' => (bool) self::setting('baa_skip', 1),
            'liveEnabled' => (bool) self::setting('baa_live', 1),
            'quickEnabled' => (bool) self::setting('baa_quick', 1),
            'readingEnabled' => (bool) self::setting('baa_reading', 1),
            'diagnosticsEnabled' => (bool) self::setting('baa_diagnostics', 1),
            'quickTitle' => $txt['baa_quick_title'],
            'readingTitle' => $txt['baa_reading_title'],
            'liveLabel' => $txt['baa_live_label'],
            'presetLabel' => $txt['baa_preset_label'],
            'jumpLabel' => $txt['baa_jump_label'],
            'altHelp' => $txt['baa_alt_help'],
            'quickOptions' => array($txt['baa_large'], $txt['baa_underlines'], $txt['baa_spacing'], $txt['baa_motion'], $txt['baa_focus']),
            'actionUrl' => $scripturl . '?action=accessibility',
            'label' => $txt['baa_check_button'],
            'close' => $txt['baa_close'],
            'noFindings' => $txt['baa_no_findings'],
            'resultTitle' => $txt['baa_results'],
            'settingsSaved' => $txt['baa_saved_local'],
            'settingsBlocked' => $txt['baa_storage_blocked'],
            'settingsReset' => $txt['baa_reset_local'],
            'skipLabel' => $txt['baa_skip_label'],
            'composerLabel' => $txt['baa_composer_label'],
            'composerHelp' => $txt['baa_composer_help'],
            'invalidColours' => $txt['baa_contrast_invalid'],
            'contrastOutput' => $txt['baa_contrast_output'],
            'contrastNormalPass' => $txt['baa_contrast_normal_pass'],
            'contrastNormalFail' => $txt['baa_contrast_normal_fail'],
            'contrastLargePass' => $txt['baa_contrast_large_pass'],
            'contrastLargeFail' => $txt['baa_contrast_large_fail'],
            'contrastCaution' => $txt['baa_contrast_caution'],
            'contrastAAAPass' => $txt['baa_contrast_aaa_pass'],
            'contrastAAAFail' => $txt['baa_contrast_aaa_fail'],
            'inputLimit' => self::MAX_INPUT,
            'onWhite' => $txt['baa_on_white'],
            'checks' => self::messages(),
        );
        // JSON_HEX flags prevent user-provided translations from becoming markup.
        $context['html_headers'] = (isset($context['html_headers']) ? $context['html_headers'] : '') .
            '<script>window.boredAccessibility=' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    public static function hookPostEnd()
    {
        // Post composer integration is entirely progressive enhancement.
        // No template mutation or posting validation is required.
        global $context;
        if (!self::enabled() || !self::setting('baa_composer', 1))
            return;
        $context['baa_posting'] = true;
        $context['html_headers'] = (isset($context['html_headers']) ? $context['html_headers'] : '') .
            '<script>window.boredAccessibilityComposer=true;</script>';
    }

    public static function messages()
    {
        // The default text supports standalone tests; SMF translations override it.
        global $txt;
        $rules = array(
            'image_alt' => array('title' => 'Image description to review', 'detail' => 'Provide meaningful alternative text for informative images. If truly decorative, use an empty alternative where the editor supports it.', 'wcag' => 'WCAG 2.2 1.1.1'),
            'image_generic' => array('title' => 'Image description may be vague', 'detail' => 'Avoid using image, picture or filename alone as an alternative description.', 'wcag' => 'WCAG 2.2 1.1.1'),
            'link_generic' => array('title' => 'Link purpose may be unclear', 'detail' => 'Use descriptive link text that makes sense with its surrounding context.', 'wcag' => 'WCAG 2.2 2.4.4'),
            'color_contrast' => array('title' => 'Text colour contrast should be checked', 'detail' => 'This estimate assumes a white background; compare the actual foreground and background. Normal text generally needs 4.5:1 for AA.', 'wcag' => 'WCAG 2.2 1.4.3'),
            'text_size' => array('title' => 'Very small text needs review', 'detail' => 'Avoid reducing the legibility of text; test resizing and zoom.', 'wcag' => 'WCAG 2.2 1.4.4'),
            'media_captions' => array('title' => 'Media alternatives need review', 'detail' => 'Check captions, transcripts and audio description as appropriate for the media type.', 'wcag' => 'WCAG 2.2 1.2.1–1.2.5'),
            'table_structure' => array('title' => 'Table structure needs review', 'detail' => 'Use tables for tabular data, and ensure header relationships are available to assistive technology.', 'wcag' => 'WCAG 2.2 1.3.1'),
            'attachment_alt' => array('title' => 'Attached media needs review', 'detail' => 'Confirm attached images have useful text alternatives and documents are accessible.', 'wcag' => 'WCAG 2.2 1.1.1 / 1.3.1'),
            'motion' => array('title' => 'Movement or flashing needs review', 'detail' => 'Avoid flashing content and provide pause/stop controls for moving information.', 'wcag' => 'WCAG 2.2 2.2.2 / 2.3.1'),
            'input_truncated' => array('title' => 'Only part of the content was checked', 'detail' => 'The checker has a size limit. Content beyond the limit has NOT been reviewed. Check shorter sections separately.', 'wcag' => 'Review scope'),
        );
        foreach ($rules as $id => &$item)
            foreach (array('title', 'detail', 'wcag') as $part)
                if (isset($txt['baa_rule_' . $id . '_' . $part]))
                    $item[$part] = $txt['baa_rule_' . $id . '_' . $part];
        unset($item);
        return $rules;
    }

    /** Parse only actual, whitespace-delimited BBCode parameters, respecting quotes. */
    private static function tagAttributes($raw)
    {
        $attributes = array();
        $length = strlen($raw);
        if ($length > 4096)
            return array();
        $i = 0;
        while ($i < $length)
        {
            while ($i < $length && ctype_space($raw[$i])) ++$i;
            if ($i === $length) break;
            $start = $i;
            if (!preg_match('/[a-z]/i', $raw[$i])) return array();
            while ($i < $length && preg_match('/[a-z0-9_-]/i', $raw[$i])) ++$i;
            $name = strtolower(substr($raw, $start, $i - $start));
            while ($i < $length && ctype_space($raw[$i])) ++$i;
            if ($i >= $length || $raw[$i] !== '=') return array();
            ++$i;
            while ($i < $length && ctype_space($raw[$i])) ++$i;
            if ($i >= $length) return array();
            $quote = ($raw[$i] === '"' || $raw[$i] === "'") ? $raw[$i++] : '';
            $start = $i;
            if ($quote !== '')
            {
                while ($i < $length && $raw[$i] !== $quote) ++$i;
                if ($i >= $length) return array();
                $value = substr($raw, $start, $i - $start);
                ++$i;
                if ($i < $length && !ctype_space($raw[$i])) return array();
            }
            else
            {
                while ($i < $length && !ctype_space($raw[$i])) ++$i;
                $value = substr($raw, $start, $i - $start);
                if ($value === '' || strpos($value, '"') !== false || strpos($value, "'") !== false) return array();
            }
            // Duplicate parameters are ambiguous; never assume the alternative is valid.
            if (array_key_exists($name, $attributes)) return array();
            $attributes[$name] = $value;
        }
        return $attributes;
    }

    /** Accept only bounded, plain-text findings from third-party integration hooks. */
    public static function normalizeFindings($items)
    {
        $out = array();
        $builtin = self::messages();
        if (!is_array($items)) return array();
        foreach ($items as $key => $item)
        {
            if (!is_array($item)) continue;
            $id = isset($item['id']) && is_string($item['id']) ? $item['id'] : (is_string($key) ? $key : '');
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $id) || isset($out[$id])) continue;
            $def = isset($builtin[$id]) ? $builtin[$id] : null;
            $title = $def ? $def['title'] : (isset($item['title']) && is_string($item['title']) ? trim($item['title']) : '');
            $detail = $def ? $def['detail'] : (isset($item['detail']) && is_string($item['detail']) ? trim($item['detail']) : '');
            $reference = $def ? $def['wcag'] : (isset($item['reference']) && is_string($item['reference']) ? trim($item['reference']) : '');
            if ($title === '' || $detail === '') continue;
            $example = isset($item['example']) && is_string($item['example']) ? $item['example'] : '';
            $out[$id] = array(
                'id' => $id,
                'title' => substr($title, 0, 160),
                'detail' => substr($detail, 0, 1200),
                'reference' => substr($reference, 0, 160),
                'example' => substr($example, 0, 240),
            );
            if (count($out) >= 50) break;
        }
        return array_values($out);
    }

    public static function hexContrast($hex, $background = '#ffffff')
    {
        $toRGB = static function ($input) {
            $input = strtolower(ltrim($input, '#'));
            if (strlen($input) === 3)
                $input = $input[0] . $input[0] . $input[1] . $input[1] . $input[2] . $input[2];
            if (!preg_match('/^[0-9a-f]{6}$/', $input))
                return null;
            $out = array();
            foreach (array(0, 2, 4) as $start)
            {
                $channel = hexdec(substr($input, $start, 2)) / 255;
                $out[] = $channel <= 0.04045 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4);
            }
            return $out[0] * 0.2126 + $out[1] * 0.7152 + $out[2] * 0.0722;
        };
        $front = $toRGB($hex);
        $back = $toRGB($background);
        return $front === null || $back === null ? null : (max($front, $back) + 0.05) / (min($front, $back) + 0.05);
    }

    public static function audit($input)
    {
        global $txt;
        $input = (string) $input;
        $wasTruncated = strlen($input) > self::MAX_INPUT;
        if ($wasTruncated)
            $input = substr($input, 0, self::MAX_INPUT);
        $found = array();
        $put = static function ($id, $example = '') use (&$found) {
            if (!isset($found[$id]))
                $found[$id] = array('id' => $id, 'example' => (string) $example);
        };
        if ($wasTruncated) $put('input_truncated');

        // One forward-only pass: malformed/unclosed BBCode cannot trigger quadratic retries.
        $offset = 0;
        $urlStack = array();
        while (preg_match('~\[(/?)(img|url)(?=[\]\s=])([^\]]{0,4096})\]~i', $input, $tag, PREG_OFFSET_CAPTURE, $offset))
        {
            $at = $tag[0][1];
            $offset = $at + strlen($tag[0][0]);
            $type = strtolower($tag[2][0]);
            $closing = $tag[1][0] === '/';
            if ($type === 'img' && !$closing)
            {
                $attrs = self::tagAttributes($tag[3][0]);
                if (!array_key_exists('alt', $attrs))
                    $put('image_alt');
                elseif (preg_match('~^(?:image|picture|photo|screenshot|file|img[0-9_.-]*)$~i', trim($attrs['alt'])))
                    $put('image_generic');
            }
            elseif ($type === 'url')
            {
                if (!$closing)
                {
                    // SMF does not support meaningful recursive links. Bound pathological nesting.
                    if (count($urlStack) < 64) $urlStack[] = $offset;
                }
                elseif (!empty($urlStack))
                {
                    $start = array_pop($urlStack);
                    $length = $at - $start;
                    if ($length >= 0 && $length <= 512)
                    {
                        // Remove formatting BBCode from the *bounded* caption, not HTML tags.
                        $caption = preg_replace('~\[[^\]]{0,256}\]~', '', substr($input, $start, $length));
                        if (preg_match('~^\s*(?:here|click here|read more|link|more|this)\s*$~i', $caption))
                            $put('link_generic');
                    }
                }
            }
        }
        if (preg_match_all('~\[color\s*=\s*#([0-9a-f]{3}|[0-9a-f]{6})\]~i', $input, $colors))
            foreach ($colors[1] as $color)
            {
                $contrast = self::hexContrast('#' . $color);
                if ($contrast !== null && $contrast < 4.5)
                    $put('color_contrast', '#' . $color . ' ' . (isset($txt['baa_on_white']) ? $txt['baa_on_white'] : 'on white') . ' ≈ ' . round($contrast, 2) . ':1');
            }
        if (preg_match('~\[size\s*=\s*(?:xx-small|x-small|(?:[1-8](?:\.[0-9]+)?)\s*(?:pt|px))\]~i', $input))
            $put('text_size');
        if (preg_match('~\[(?:youtube|video|audio|flash)(?=[=\s\]])~i', $input))
            $put('media_captions');
        if (preg_match('~\[table(?:[=\s\]])~i', $input))
            $put('table_structure');
        if (preg_match('~\[(?:attach|attachimg)(?:[=\s\]])~i', $input))
            $put('attachment_alt');
        if (preg_match('~\[(?:move|marquee|flash)(?:[=\s\]])~i', $input))
            $put('motion');

        if (function_exists('call_integration_hook'))
        {
            // Hook may append ['id','title','detail','reference','example'] findings.
            // The output is schema-validated, length-limited, then HTML-escaped by renderer.
            call_integration_hook('integrate_baa_audit', array(&$found, $input));
        }
        return self::normalizeFindings($found);
    }

    public static function dispatch($inProfile = false)
    {
        global $context, $txt, $scripturl;
        if (!self::enabled())
            fatal_lang_error('no_access', false);
        loadLanguage('AccessibilityAssistant');
        loadTemplate('AccessibilityAssistant');
        $context['page_title'] = $txt['baa_title'];
        $context['sub_template'] = 'baa_home';
        $context['baa_result'] = null;
        $context['baa_content'] = '';
        $context['baa_posted'] = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkSession('post');
            validateToken('baa_check', 'post');
            $content = isset($_POST['baa_content']) && is_string($_POST['baa_content']) ? $_POST['baa_content'] : '';
            $context['baa_content'] = substr($content, 0, self::MAX_INPUT);
            // Audit the uncut string so the user is told when the limit is reached.
            $context['baa_result'] = self::audit($content);
            $context['baa_posted'] = true;
        }
        createToken('baa_check', 'post');
        $context['baa_check_action'] = $inProfile ? $scripturl . '?action=profile;area=accessibilityassistant;u=' . (int) $GLOBALS['user_info']['id'] : $scripturl . '?action=accessibility';
    }

    public static function admin()
    {
        global $context, $txt, $scripturl;
        isAllowedTo('admin_forum');
        loadLanguage('AccessibilityAssistant');
        loadTemplate('AccessibilityAssistant');
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkSession('post');
            validateToken('baa_admin', 'post');
            $settings = array();
            foreach (array('baa_enabled', 'baa_composer', 'baa_skip', 'baa_live', 'baa_quick', 'baa_reading', 'baa_diagnostics') as $name)
                $settings[$name] = !empty($_POST[$name]) ? 1 : 0;
            updateSettings($settings);
            // Ensure any settings cache used during this request reflects the save.
            global $modSettings;
            $modSettings = array_merge($modSettings, $settings);
            redirectexit('action=admin;area=accessibilityassistant;sa=settings');
        }
        // Safe, read-only diagnostic summary: no forum posts or member data retained.
        $context['baa_diagnostics'] = array(
            'php' => PHP_VERSION,
            'smf' => defined('SMF_VERSION') ? SMF_VERSION : (isset($GLOBALS['forum_version']) ? $GLOBALS['forum_version'] : 'Unknown'),
            'mod' => self::VERSION,
            'composer' => self::setting('baa_composer', 1),
            'live' => self::setting('baa_live', 1),
            'quick' => self::setting('baa_quick', 1),
            'reading' => self::setting('baa_reading', 1),
            'extension' => function_exists('call_integration_hook') ? 'Available' : 'Unavailable',
        );
        // External mods may add bounded informational diagnostics.
        if (function_exists('call_integration_hook'))
            call_integration_hook('integrate_baa_diagnostics', array(&$context['baa_diagnostics']));
        $context['baa_diagnostics'] = array_slice(array_filter($context['baa_diagnostics'], static function ($value) {
            return is_scalar($value) && strlen((string) $value) < 240;
        }), 0, 30, true);
        createToken('baa_admin', 'post');
        $context['page_title'] = $txt['baa_title'] . ' – ' . $txt['baa_admin'];
        $context['sub_template'] = 'baa_admin';
        $context['baa_admin_action'] = $scripturl . '?action=admin;area=accessibilityassistant;sa=settings';
    }
}

function baa_dispatch()
{
    BoredAccessibilityAssistant::dispatch();
}

function baa_admin()
{
    BoredAccessibilityAssistant::admin();
}

function baa_profile()
{
    BoredAccessibilityAssistant::profile();
}
