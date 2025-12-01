<!-- Render any debug data -->
<?php
if (function_exists('renderDebugData')) {
    renderDebugData();
}
?>
</div> <!-- /inner content -->
</main>
</div> <!-- /flex wrapper -->

<!-- Monaco Editor for JSON Viewing -->
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.34.1/min/vs/loader.js"></script>
<script src="assets/app.js"></script>

<script>
    // Navbar user menu toggle
    (function () {
        const btn = document.getElementById('userMenuButton');
        const menu = document.getElementById('userMenu');
        if (!btn || !menu) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });

        document.addEventListener('click', function () {
            menu.classList.add('hidden');
        });
    })();

    // Initialize Monaco Editor for all JSON containers
    (function () {
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.34.1/min/vs' } });

        require(['vs/editor/editor.main'], function () {
            const containers = document.querySelectorAll('.monaco-container');

            containers.forEach(container => {
                const content = container.getAttribute('data-content');

                try {
                    const monEditor = monaco.editor.create(container, {
                        value: content,
                        language: 'json',
                        theme: 'vs-dark',
                        readOnly: true,
                        folding: true,
                        automaticLayout: true,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false,
                        lineNumbers: 'on',
                        wordWrap: 'on',
                    });

                    setTimeout(() => {
                        const foldLevel2 = monEditor.getAction && monEditor.getAction('editor.foldLevel2');
                        if (foldLevel2) {
                            try { foldLevel2.run(); } catch (e) { /* ignore */ }
                            return;
                        }

                        const foldAll = monEditor.getAction && monEditor.getAction('editor.foldAll');
                        const unfoldLevel1 = monEditor.getAction && monEditor.getAction('editor.unfoldLevel1');

                        if (foldAll) {
                            try {
                                foldAll.run();
                                if (unfoldLevel1) {
                                    setTimeout(() => {
                                        try { unfoldLevel1.run(); } catch (e) { /* ignore */ }
                                    }, 80);
                                }
                            } catch (e) {
                                /* ignore */
                            }
                        } else {
                            try {
                                const folding = monEditor.getContribution && monEditor.getContribution('editor.contrib.folding');
                                if (folding && folding.getFoldingModel) {
                                    folding.getFoldingModel().then(fm => {
                                        if (!fm) return;
                                        for (let i = 0; i < fm.regions.length; i++) {
                                            fm.setCollapsed(i, true);
                                        }
                                        for (let i = 0; i < fm.regions.length; i++) {
                                            const startLine = fm.regions.getStartLineNumber(i);
                                            const indent = monEditor.getModel().getLineFirstNonWhitespaceColumn(startLine);
                                            if (indent <= 1) {
                                                fm.setCollapsed(i, false);
                                            }
                                        }
                                    }).catch(() => { /* ignore */ });
                                }
                            } catch (e) { /* ignore */ }
                        }
                    }, 120);
                } catch (e) {
                    console.error('Failed to create Monaco editor:', e);
                    container.innerHTML = `<pre class="whitespace-pre-wrap p-3 bg-gray-800 text-gray-100 rounded">${escapeHtml(content)}</pre>`;
                }
            });
        });

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    })();
</script>
</body>

</html>