</div> <!-- container -->

<!-- Render any debug data -->
<?php
if (function_exists('renderDebugData')) {
    renderDebugData();
}
?>

<!-- Monaco Editor for JSON Viewing -->
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.34.1/min/vs/loader.js"></script>
<script src="/tools/msdd/assets/app.js"></script>

<script>    // Initialize Monaco Editor for all JSON containers
    (function () {
        console.log('Monaco initialization starting...');
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.34.1/min/vs' } });

        require(['vs/editor/editor.main'], function () {
            console.log('Monaco editor module loaded');
            const containers = document.querySelectorAll('.monaco-container');
            console.log('Found ' + containers.length + ' Monaco containers');

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

                    // Give Monaco a moment to compute folding ranges, then fold to show only top-level.
                    setTimeout(() => {
                        // Try folding to level 2 (keeps top-level expanded, folds everything else).
                        const foldLevel2 = monEditor.getAction && monEditor.getAction('editor.foldLevel2');
                        if (foldLevel2) {
                            try { foldLevel2.run(); } catch (e) { /* ignore */ }
                            return;
                        }

                        // Fallback: fold all then attempt to unfold first-level ranges if the actions exist.
                        const foldAll = monEditor.getAction && monEditor.getAction('editor.foldAll');
                        const unfoldLevel1 = monEditor.getAction && monEditor.getAction('editor.unfoldLevel1');

                        if (foldAll) {
                            try {
                                const res = foldAll.run();
                                // If we have an unfoldLevel1 action, run it after folding everything.
                                if (unfoldLevel1) {
                                    // run after slight delay to ensure folding finished
                                    setTimeout(() => {
                                        try { unfoldLevel1.run(); } catch (e) { /* ignore */ }
                                    }, 80);
                                }
                            } catch (e) {
                                // ignore
                            }
                        } else {
                            // Last-resort manual approach using folding contribution:
                            try {
                                const folding = monEditor.getContribution && monEditor.getContribution('editor.contrib.folding');
                                if (folding && folding.getFoldingModel) {
                                    folding.getFoldingModel().then(fm => {
                                        if (!fm) return;
                                        // collapse everything first
                                        for (let i = 0; i < fm.regions.length; i++) {
                                            fm.setCollapsed(i, true);
                                        }
                                        // then expand top-level regions (those starting at indent level 1)
                                        for (let i = 0; i < fm.regions.length; i++) {
                                            const startLine = fm.regions.getStartLineNumber(i);
                                            const endLine = fm.regions.getEndLineNumber(i);
                                            const indent = monEditor.getModel().getLineFirstNonWhitespaceColumn(startLine);
                                            // treat indent of 1 (or column 1) as top-level
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