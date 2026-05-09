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

// 2. 自動判別ロジック: キャプションに「生成」が含まれる場合に「AI生成メディアを含む」をオンにする
if (window.wp && wp.data) {
    const { subscribe, select, dispatch } = wp.data;

    let isChecking = false;

    subscribe(() => {
        if (isChecking) return;

        const blocks = select('core/block-editor').getBlocks();
        const hasGeneratedCaption = blocks.some(block => {
            if (block.name === 'core/image') {
                const caption = block.attributes.caption || '';
                return caption.includes('生成');
            }
            return false;
        });

        if (hasGeneratedCaption) {
            const currentMeta = select('core/editor').getEditedPostAttribute('meta');
            // _node_is_ai_generated が false (または未定義) の場合のみ更新
            if (currentMeta && !currentMeta._node_is_ai_generated) {
                isChecking = true;
                dispatch('core/editor').editPost({
                    meta: { _node_is_ai_generated: true }
                });
                // 再帰的な呼び出しを防ぐためのフラグ。エディタのステート更新後に戻す。
                setTimeout(() => { isChecking = false; }, 500);
            }
        }
    });
}

// 3. TinyMCE (Classic Editor) Support
// TinyMCE is handled via PHP filters, but we can ensure the UI is consistent
document.addEventListener('DOMContentLoaded', () => {
    // Custom logic if needed for Admin UI
});
