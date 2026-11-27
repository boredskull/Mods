<?php
/* CLI-only regression smoke tests. No live SMF installation required. ISC. */
define('SMF', 1);
$txt = array();
require __DIR__ . '/../Sources/AccessibilityAssistant.php';
function ensure($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
ensure(BoredAccessibilityAssistant::VERSION === '1.3.1', 'version');
ensure(BoredAccessibilityAssistant::hexContrast('#000000', '#ffffff') > 20, 'high contrast');
ensure(BoredAccessibilityAssistant::hexContrast('#ffffff', '#ffffff') === 1.0, 'equal colours');
ensure(BoredAccessibilityAssistant::hexContrast('invalid') === null, 'invalid colour');
function ids($text) { return array_column(BoredAccessibilityAssistant::audit($text), 'id'); }
ensure(in_array('image_alt', ids('[img]https://example.org/test.png[/img]'), true), 'missing alt');
ensure(!in_array('image_alt', ids('[img alt="Dog on a beach"]https://example.org/test.png[/img]'), true), 'meaningful alt');
ensure(in_array('image_generic', ids('[img alt="image"]x[/img]'), true), 'vague alt');
ensure(in_array('link_generic', ids('[url=https://example.org]click here[/url]'), true), 'vague link');
ensure(!in_array('link_generic', ids('[url=https://example.org]Read the forum guidelines[/url]'), true), 'descriptive link');
ensure(in_array('input_truncated', ids(str_repeat('X', 200001)), true), 'length limit');
ensure(count(ids('[img]' . str_repeat('X', 300000))) <= 2, 'bounded malformed tags');
echo "PHP smoke checks passed.\n";
