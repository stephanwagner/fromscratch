<?php

defined('ABSPATH') || exit;

add_action('admin_head', function () {
?>
<style>
    /* PAGE */
    .upload-php #wpbody-content .wrap {
        visibility: hidden;
    }
    .upload-php.fs-ready #wpbody-content .wrap {
        visibility: visible;
    }

    /* MODAL */
    .media-frame.fs-hide .media-frame-content {
        visibility: hidden;
    }
    .media-frame.fs-ready .media-frame-content {
        visibility: visible;
    }

    .fs-layout {
        display: flex;
        gap: 20px;
        margin-top: 12px;
    }

    .media-frame .fs-layout {
        height: 100%;
        margin-top: 0;
    }

    .fs-sidebar {
        width: 260px;
        padding: 16px;
        background: #fff;
        border: 1px solid #dcdcde;
    }

    .media-frame .fs-sidebar {
        border-right: 1px solid #ddd;
        border-left: none;
        border-top: none;
        border-bottom: none;
    }

    .fs-main {
        flex: 1;
        min-width: 0;
    }
</style>
<?php
});

add_action('admin_footer', function () {

    if (!current_user_can('upload_files')) return;
?>
<script>
(function() {

    // ==========================================
    // MEDIA PAGE
    // ==========================================
    function initPage() {

        if (!document.body.classList.contains('upload-php')) return;

        const wrap = document.querySelector('#wpbody-content .wrap');

        if (!wrap) {
            requestAnimationFrame(initPage);
            return;
        }

        if (wrap.dataset.fsDone) {
            document.body.classList.add('fs-ready');
            return;
        }

        const sidebar = document.createElement('div');
        sidebar.className = 'fs-sidebar';
        sidebar.innerHTML = '<strong>Folders</strong>';

        const layout = document.createElement('div');
        layout.className = 'fs-layout';

        const main = document.createElement('div');
        main.className = 'fs-main';

        layout.appendChild(sidebar);
        layout.appendChild(main);

        const headerEnd = wrap.querySelector('hr.wp-header-end');

        if (headerEnd && headerEnd.nextSibling) {
            wrap.insertBefore(layout, headerEnd.nextSibling);
        } else {
            wrap.appendChild(layout);
        }

        Array.from(wrap.children).forEach(node => {
            if (
                node === layout ||
                node.tagName === 'H1' ||
                node.classList.contains('page-title-action') ||
                node.classList.contains('wp-header-end')
            ) return;

            main.appendChild(node);
        });

        wrap.dataset.fsDone = '1';
        document.body.classList.add('fs-ready');
    }

    initPage();

    // ==========================================
    // MEDIA MODAL (THIS is the fix)
    // ==========================================
    function initModal() {

        const frame = document.querySelector('.media-frame');

        // wait until modal exists
        if (!frame) {
            requestAnimationFrame(initModal);
            return;
        }

        if (frame.dataset.fsDone) return;

        frame.classList.add('fs-hide');

        requestAnimationFrame(() => {

            const content = frame.querySelector('.media-frame-content');

            if (!content) {
                frame.classList.remove('fs-hide');
                return;
            }

            const sidebar = document.createElement('div');
            sidebar.className = 'fs-sidebar';
            sidebar.innerHTML = '<strong>Folders</strong>';

            const layout = document.createElement('div');
            layout.className = 'fs-layout';

            const main = document.createElement('div');
            main.className = 'fs-main';

            while (content.firstChild) {
                main.appendChild(content.firstChild);
            }

            layout.appendChild(sidebar);
            layout.appendChild(main);
            content.appendChild(layout);

            frame.dataset.fsDone = '1';

            frame.classList.remove('fs-hide');
            frame.classList.add('fs-ready');

        });
    }

    // 🔥 Trigger modal detection
    document.addEventListener('click', function() {
        setTimeout(initModal, 100);
    });

})();
</script>
<?php
});