<?php

declare(strict_types=1);

return [

    'titles' => [
        'pages' => 'Pages · Inlay',
        'create_page' => 'Create page · Inlay',
        'edit_page' => ':page · Inlay',
        'settings' => 'Settings · Inlay',
    ],

    'nav' => [
        'brand' => 'Inlay',
        'pages' => 'Pages',
        'settings' => 'Settings',
    ],

    'common' => [
        'content_eyebrow' => 'Content',
        'saving' => 'Saving…',
        'saved' => 'Saved',
        'remove' => 'Remove',
    ],

    'status' => [
        'draft' => 'draft',
        'published' => 'published',
    ],

    'pages' => [
        'heading' => 'Pages',
        'search_placeholder' => 'Search pages…',
        'new_page' => 'New page',
        'filter_label' => 'Filter pages by status',
        'filter_all' => 'All',
        'filter_draft' => 'Draft',
        'filter_published' => 'Published',
        'empty_filtered' => 'No pages match this filter.',
        'empty' => 'No pages yet. Create the first one to get started.',
        'column_title' => 'Title',
        'column_status' => 'Status',
        'column_updated' => 'Updated',
        'no_public_path' => 'No public path',
        'homepage_badge' => 'Homepage',
        'route_conflict_title' => ":route answers this page's URL first.",
        'hidden_by_route' => 'Hidden by another route',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this page? This cannot be undone.',
    ],

    'page_fields' => [
        'title' => 'Title',
        'page_type' => 'Page type',
        'public_path' => 'Public path',
        'path_placeholder' => 'about/team',
        'set_as_homepage' => 'Set as homepage',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
    ],

    'create' => [
        'heading' => 'New page',
        'cancel' => 'Cancel',
        'submit' => 'Create page',
        'page_type_help' => 'Controls which sections this page may contain, and which structure it must retain. Cannot be changed after the page is created.',
        'path_help' => 'Optional. Leave blank for a page with no public URL of its own - your application can still render it directly via :method.',
        'will_publish_at' => 'Will publish at :url.',
        'homepage_help' => "Serves this page at your site's root URL instead of the public path above. Replaces the current homepage, if one is already set.",
        'meta_title_help' => 'Shown in search results and browser tabs. Falls back to the page title above when left blank.',
        'meta_description_help' => 'A short summary shown in search results. Left blank, no description is output.',
    ],

    'route_conflict' => [
        'title' => "This page won't show at :url",
        'body' => ':route answers that URL first. Remove or rename that route, give this page another path, or make sure :method is the last route your application registers.',
    ],

    'editor' => [
        'all_pages' => 'All pages',
        'announce_saved' => 'Saved',
        'announce_published' => 'Published successfully',
        'announce_restored' => 'Revision restored',
        'announce_restored_with_violations' => 'Restored to draft - not published',
        'announce_publish_blocked' => 'Publish blocked - see the notice below',
        'page_settings' => 'Page Settings',
        'history' => 'History',
        'keyboard_shortcuts' => 'Keyboard shortcuts',
        'publish' => 'Publish',
        'publishing' => 'Publishing…',
        'violations_title' => "This page can't be published yet",
        'panel_label' => 'Editor panel',
        'panel_edit' => 'Edit',
        'panel_preview' => 'Preview',
        'section_unregistered' => 'No longer registered',
        'resize_panel' => 'Resize the sections panel',
        'expand_panel' => 'Expand panel',
        'collapse_panel' => 'Collapse panel',
        'announce_section_settings' => 'Editing section settings',
        'announce_page_settings' => 'Editing page settings',
        'announce_history' => 'Showing version history',
        'announce_sections' => 'Showing sections',
    ],

    'sections' => [
        'heading' => 'Sections',
        'add' => '+ Add',
        'list_label' => 'Page sections',
        'locked' => 'Locked',
        'locked_title' => "Locked by :definition - can't be moved or removed",
        'required' => 'Required',
        'required_title' => 'Required by :definition',
        'duplicate' => 'Duplicate',
        'duplicate_label' => 'Duplicate :section',
        'delete' => 'Delete',
        'delete_label' => 'Delete :section',
        'delete_confirm' => 'Remove this section from the page?',
        'locked_cannot_remove' => "Locked by :definition - can't be removed",
        'required_cannot_remove' => 'Required by :definition - remove another instance first',
        'cannot_remove_label' => ':section cannot be removed',
        'empty_title' => 'No sections yet',
        'empty' => 'Add a section to start building this page.',
        'back' => 'Sections',
        'fallback_name' => 'Section',
        'select_prompt' => 'Select a section from the list to edit its settings.',
    ],

    'page_settings' => [
        'general' => 'General',
        'type_unresolved' => "This page's type is no longer registered. A developer needs to resolve this before publishing.",
        'page_type_help' => 'Controls which sections this page may contain. Cannot be changed after creation.',
        'path_help' => 'Optional. Leave blank for a page with no public URL of its own.',
        'publishes_at' => 'Publishes at :url.',
        'homepage_help' => "Serves this page at your site's root URL. Replaces the current homepage, if one is already set.",
        'search_metadata' => 'Search metadata',
        'meta_title_help' => 'Falls back to the title above when left blank.',
        'publishing' => 'Publishing',
        'status' => 'Status:',
        'last_published' => 'Last published :time.',
        'last_published_labelled' => 'Last published :time (:label).',
        'never_published' => 'Never published.',
        'live_notice' => "Public path and homepage status take effect as soon as you save - they aren't part of this page's unpublished section content, so they don't wait for Publish.",
        'save' => 'Save page settings',
    ],

    'history' => [
        'heading' => 'Version history',
        'restore' => 'Restore',
        'empty' => 'No revisions yet - publish this page to create the first one.',
    ],

    'preview' => [
        'no_public_path' => "This page has no public path - it isn't reachable at a URL of its own",
        'not_published' => 'Not published',
        'viewport_label' => 'Preview viewport',
        'desktop' => 'Desktop',
        'desktop_label' => 'Desktop viewport',
        'tablet' => 'Tablet',
        'tablet_label' => 'Tablet viewport',
        'mobile' => 'Mobile',
        'mobile_label' => 'Mobile viewport',
        'refresh' => 'Refresh preview',
        'frame_title' => 'Page preview',
        'empty_title' => 'Nothing here yet',
        'empty' => 'Add a section to see it appear here.',
        'fallback_title' => 'Preview',
        'fallback_notice' => 'Unstyled fallback - configure :method to preview using your real design.',
    ],

    'picker' => [
        'label' => 'Add a section',
        'search_placeholder' => 'Search sections…',
        'search_label' => 'Search sections',
        'hint_navigate' => 'navigate',
        'hint_add' => 'add',
        'hint_close' => 'close',
        'recent' => 'Recent',
        'definition_unresolved' => "This page's type is no longer registered, so Inlay cannot tell which sections it allows. A developer needs to resolve this before sections can be added.",
        'none_allowed' => 'No more sections are available for this page type.',
        'none_registered' => 'No sections have been registered yet. Add one to :config or via :method.',
        'no_results' => 'No sections match “:query”.',
    ],

    'keys' => [
        'esc' => 'Esc',
        'alt' => 'Alt',
        'delete' => 'Delete',
    ],

    'shortcuts' => [
        'title' => 'Keyboard shortcuts',
        'focus_sections' => 'Move focus between sections',
        'open_settings' => "Open the focused section's settings",
        'reorder' => 'Reorder the focused section',
        'delete' => 'Delete the focused section',
        'back' => 'Back to the section list',
        'resize' => 'Resize the sections panel',
        'picker' => 'In the section picker: navigate / add',
        'show_panel' => 'Show this panel',
    ],

    'settings' => [
        'eyebrow' => 'Site',
        'heading' => 'Settings',
        'empty' => 'No site settings are registered.',
    ],

    'fields' => [
        'required' => 'This field is required.',
        'replace_image' => 'Replace image',
        'upload_image' => 'Click to upload an image',
        'uploading' => 'Uploading...',
        'link_label_placeholder' => 'Label',
        'link_url_placeholder' => 'URL',
        'open_in_new_tab' => 'Open in new tab',
        'remove_link' => 'Remove link',
        'add_item' => '+ Add item',
    ],

    'blocks' => [
        'unknown' => 'Unknown block',
        'move_up' => 'Move :block up',
        'move_down' => 'Move :block down',
        'duplicate' => 'Duplicate :block',
        'remove' => 'Remove :block',
        'remove_confirm' => 'Remove this block?',
        'none_registered' => 'No blocks are registered for this area.',
        'add' => '+ Add block',
    ],

    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
    ],

];
