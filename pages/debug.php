<?php if (isset($_GET['debug'])): ?>
    <div class="mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <?php
            monacoEditor(["whmcs_data" => $whmcs_data, "dicker_data" => $dicker_data], 'Debug Data');
            ?>
        </div>
    </div>
<?php endif; ?>