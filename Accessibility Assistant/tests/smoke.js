/* Browser-free checks for the safe parser interface. ISC. */
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const noop = () => {};
const context = {
  window: { boredAccessibility: { inputLimit: 200000, checks: {} }, localStorage: { getItem: () => null, setItem: noop } },
  document: { documentElement: { classList: { toggle: noop } }, readyState: 'loading', addEventListener: noop, dispatchEvent: noop },
  Event: function Event() {}
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(__dirname + '/../Themes/default/scripts/accessibility_assistant.js', 'utf8'), context);
const check = context.window.baaAccessibilityCheck;
const ids = text => check(text).map(x => x.id);
assert(ids('[img]x[/img]').includes('image_alt'));
assert(!ids('[img alt="Dog on beach"]x[/img]').includes('image_alt'));
assert(ids('[url=x]click here[/url]').includes('link_generic'));
assert(ids('x'.repeat(200001)).includes('input_truncated'));
const api = context.window.BoredAccessibilityAPI;
assert(api.addRule('test_rule', 'Test', 'Detail', 'Example', text => text.includes('testword')));
assert(ids('testword').includes('test_rule'));
console.log('JavaScript smoke checks passed.');

// Regression: navigational text must scale and the floating control must not be clipped at right edge.
const css = fs.readFileSync(__dirname + '/../Themes/default/css/accessibility_assistant.css', 'utf8');
assert(css.includes('html.baa-pref-largeText :is(#main_menu, #menu_nav'), 'navigation scaling missing');
assert(css.includes('left: max(1rem, env(safe-area-inset-left'), 'left-anchored button missing');
assert(css.includes('right: auto !important'), 'right theme positioning is not reset');
assert(css.includes('.baa-quick-panel[hidden] { display: none !important; }'), 'hidden panel override missing');
