<?php
/**
 * Contract checks for Quote Edit Screen action menu behavior.
 */

function test_quote_edit_screen_action_menu_preview_is_submenu_only()
{
    $source_path = dirname(__DIR__, 2) . '/inc/modules/pricing/class-quote-edit-screen.php';
    Assert::true(file_exists($source_path), 'Quote edit screen source should exist');
    if (!file_exists($source_path)) {
        return;
    }

    $source = (string) file_get_contents($source_path);
    Assert::true(
        strpos($source, 'data-action="preview"') !== false,
        'Preview action must exist in split submenu'
    );
    Assert::same(
        false,
        strpos($source, 'id="tah-action-preview"') !== false,
        'Preview should not render as a top-level header button'
    );
}

function test_quote_edit_screen_action_menu_delete_uses_primary_and_fallback_urls()
{
    $source_path = dirname(__DIR__, 2) . '/inc/modules/pricing/class-quote-edit-screen.php';
    Assert::true(file_exists($source_path), 'Quote edit screen source should exist');
    if (!file_exists($source_path)) {
        return;
    }

    $source = (string) file_get_contents($source_path);
    Assert::true(
        strpos($source, 'data-delete-url="') !== false,
        'Delete action should carry server-provided delete URL'
    );
    Assert::true(
        strpos($source, ".data('deleteUrl')") !== false,
        'Delete action script should read server-provided delete URL'
    );
    Assert::true(
        strpos($source, "$('#tah-action-menu').on('click', '[data-action]'") !== false,
        'Submenu action handlers must be bound on #tah-action-menu to survive stopPropagation'
    );
    Assert::same(
        false,
        strpos($source, "$(document).on('click', '#tah-action-menu [data-action]'") !== false,
        'Submenu action handlers should not be delegated on document'
    );
    Assert::true(
        strpos($source, '#submitdiv #delete-action a.submitdelete, #submitdiv a.submitdelete') !== false,
        'Delete action script should fallback to native submitdiv delete link'
    );
    Assert::true(
        strpos($source, 'window.location.assign(deleteUrl)') !== false,
        'Delete action should navigate directly to the resolved delete URL'
    );
}

// Run Tests
test_quote_edit_screen_action_menu_preview_is_submenu_only();
test_quote_edit_screen_action_menu_delete_uses_primary_and_fallback_urls();
