<?php
/**
 * Helper Functions for O365 Sync Dashboard
 * 
 * monacoEditor($data, $title, $height) - Display JSON data in Monaco editor
 * dd($data, $title) - Queue data for debug display at bottom of page (does NOT stop execution)
 * renderDebugData() - Render all queued debug data (called automatically in footer)
 */

function monacoEditor($content, $title = 'Debug Output', $height = '360px')
{
    $id = 'monaco-' . bin2hex(random_bytes(4));
    $jsonPretty = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    echo '<div class="mb-8" data-monaco-editor>';
    echo '<div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">';
    echo '<h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">';
    echo '<svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>';
    echo '</svg>';
    echo htmlspecialchars($title);
    echo '</h3>';
    echo '<div class="relative">';
    echo '<div id="' . $id . '" class="monaco-container" data-content="' . htmlspecialchars($jsonPretty, ENT_QUOTES) . '" style="height:' . $height . ';border:1px solid #374151;border-radius:6px;overflow:hidden;"></div>';
    echo '<noscript><pre class="whitespace-pre-wrap p-3 bg-gray-800 text-gray-100 rounded">' . htmlspecialchars($jsonPretty) . '</pre></noscript>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function dd($data, $title = 'Debug Output')
{
    // Store the debug data globally to render later
    global $debugData;
    if (!isset($debugData)) {
        $debugData = [];
    }
    $debugData[] = [
        'data' => $data,
        'title' => $title
    ];

}

function renderDebugData()
{
    global $debugData;
    if (!empty($debugData)) {
        echo '<div class="mt-8 border-t-4 border-red-500 pt-8">';
        echo '<h2 class="text-2xl font-bold text-red-600 dark:text-red-400 mb-4">🐛 Debug Output</h2>';
        foreach ($debugData as $debug) {
            monacoEditor($debug['data'], $debug['title']);
        }
        echo '</div>';
    }
}