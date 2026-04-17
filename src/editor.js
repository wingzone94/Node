/**
 * Post Editor Enhancements (Gutenberg & TinyMCE)
 */

// 1. Gutenberg (Block Editor) Support
if (window.wp && wp.richText) {
    const { registerFormatType, removeFormat } = wp.richText;
    const { RichTextToolbarButton } = wp.blockEditor;
    const { __ } = wp.i18n;

    registerFormatType('node/clear-format', {
        title: __('Clear Selection Styles', 'node'),
        tagName: 'span',
        className: null,
        edit({ value, onChange }) {
            return wp.element.createElement(RichTextToolbarButton, {
                icon: 'editor-removeformatting',
                title: __('Clear Formatting (Bold, Italic, etc.)', 'node'),
                onClick: () => {
                    let newValue = value;
                    const formatsToClear = [
                        'core/bold',
                        'core/italic',
                        'core/underline',
                        'core/strikethrough',
                        'core/link'
                    ];
                    formatsToClear.forEach(format => {
                        newValue = removeFormat(newValue, format);
                    });
                    onChange(newValue);
                },
            });
        },
    });
}

// 2. TinyMCE (Classic Editor) Support
// TinyMCE is handled via PHP filters, but we can ensure the UI is consistent
document.addEventListener('DOMContentLoaded', () => {
    // Custom logic if needed for Admin UI
});
