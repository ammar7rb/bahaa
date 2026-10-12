const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { test } = require('node:test');
const source = fs.readFileSync('resources/views/layouts/admin/app.blade.php', 'utf8')
  .match(/<script>([\s\S]*?)<\/script>/)[1]
  .replace(/\{\{\s*translate\('([^']+)'\)\s*\}\}/g, '$1');

function boot(preference, blockedStorage = false) {
  const root = {}, body = {}, button = {}, persisted = { ba_admin_theme: preference };
  const node = values => ({ setAttribute: (key, value) => { values[key] = value; } });
  const context = {
    window: {},
    localStorage: {
      getItem: key => { if (blockedStorage) throw Error('blocked'); return persisted[key]; },
      setItem: (key, value) => { if (blockedStorage) throw Error('blocked'); persisted[key] = value; },
    },
    document: {
      documentElement: { ...node(root), getAttribute: key => root[key] },
      body: node(body),
      querySelectorAll: selector => selector === '[data-admin-theme-toggle]' ? [node(button)] : [],
      addEventListener: (_, fn) => fn(),
    },
  };
  vm.runInNewContext(source, context);
  return { context, root, body, button, persisted };
}

test('admin preference survives reload and synchronizes accessible theme controls', () => {
  const first = boot('light');
  first.context.window.baToggleAdminTheme();
  assert.equal(first.persisted.ba_admin_theme, 'dark');
  assert.equal(first.root['data-ba-admin-theme'], 'dark');
  assert.equal(first.body['data-bs-theme'], 'dark');
  assert.equal(first.button['aria-pressed'], 'true');
  assert.equal(first.button['aria-label'], 'light_mode');
  const reload = boot(first.persisted.ba_admin_theme);
  assert.equal(reload.root['data-ba-admin-theme'], 'dark');
  reload.context.window.baToggleAdminTheme();
  assert.equal(reload.persisted.ba_admin_theme, 'light');
});

test('blocked browser storage does not break theme interaction', () => {
  const page = boot(null, true);
  page.context.window.baToggleAdminTheme();
  assert.equal(page.root['data-ba-admin-theme'], 'dark');
});
