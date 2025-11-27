// O365 Sync Dashboard - Main JavaScript Application

// Exception Management
function addException(clientId, msc, whmcsQty, dickerQty, contextInfo, applyTo = 'client', subscriptionId = '', productId = null) {
    // Prompt user for reason
    const reason = prompt('Enter reason for exception:\n\nContext: ' + contextInfo, '');
    
    if (reason === null || reason.trim() === '') {
        alert('Exception not added - reason is required');
        return;
    }

    const formData = new URLSearchParams({
        action: 'add_exception',
        client_id: clientId,
        manufacturer_stock_code: msc,
        expected_whmcs_qty: whmcsQty,
        expected_dicker_qty: dickerQty,
        reason: reason.trim(),
        apply_to: applyTo,
        subscription_id: subscriptionId
    });    // Add product_id if provided (must be a number, not string 'null')
    if (productId !== null && productId !== 'null' && productId !== '') {
        formData.append('product_id', productId);
        console.log('Adding product_id to exception:', productId);
    } else {
        console.log('Skipping product_id (value is null/empty):', productId);
    }

    console.log('Exception form data:', {
        client_id: clientId,
        product_id: productId,
        msc: msc,
        whmcs_qty: whmcsQty,
        dicker_qty: dickerQty,
        reason: reason.trim(),
        apply_to: applyTo
    });

    return fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Exception added successfully');
                location.reload();
            } else {
                alert('Failed to add exception: ' + (res.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Network error: ' + err.message);
        });
}

function removeException(clientId, msc, subscriptionId = '') {
    if (!confirm('Are you sure you want to remove this exception?')) {
        return;
    }

    const formData = new URLSearchParams({
        action: 'remove_exception',
        client_id: clientId,
        manufacturer_stock_code: msc,
        subscription_id: subscriptionId
    });

    return fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Exception removed successfully');
                location.reload();
            } else {
                alert('Failed to remove exception');
            }
        })
        .catch(err => {
            alert('Network error: ' + err.message);
        });
}

// Mapping Management
function initMappingUI() {
    const saveMappingBtn = document.getElementById('saveMappingBtn2');
    const rematchBtn = document.getElementById('rematchBtn2');

    if (!saveMappingBtn || !rematchBtn) {
        return; // Not on the mapping page
    }

    // Initialize SortableJS for drag & drop
    const pool = document.getElementById('dickerList');
    const dropzones = document.querySelectorAll('.whmcs-dropzone');

    if (pool && typeof Sortable !== 'undefined') {
        // Pool is sortable and items can be dragged out
        Sortable.create(pool, {
            group: 'shared',
            animation: 150,
            sort: true,
            ghostClass: 'opacity-50',
            dragClass: 'cursor-grabbing'
        });

        // Each WHMCS box is a dropzone
        dropzones.forEach(zone => {
            Sortable.create(zone, {
                group: 'shared',
                animation: 150,
                sort: true,
                ghostClass: 'opacity-50',
                dragClass: 'cursor-grabbing'
            });
        });
    }

    // Collect mapping data from UI state
    function collectMapping() {
        const result = { d: [], dicker: { packages: [] }, whmcs: { packages: [] } };

        document.querySelectorAll('.whmcs-box-container').forEach(box => {
            const wid = box.getAttribute('data-whmcs-id');
            const nameEl = box.querySelector('.font-semibold');
            const name = nameEl ? nameEl.textContent.trim() : '';

            const dickerItems = [];
            const zone = box.querySelector('.whmcs-dropzone');
            if (zone) {
                zone.querySelectorAll('.dicker-item').forEach(item => {
                    try {
                        const json = JSON.parse(item.getAttribute('data-json'));
                        dickerItems.push(json);
                    } catch (e) {
                        console.error('Failed to parse dicker item:', e);
                    }
                });
            }

            result.d.push({
                whmcs_id: isNaN(wid) ? wid : parseInt(wid),
                whmcs_product_name: name,
                dicker: dickerItems
            });
        });

        return result;
    }

    // Save mapping
    saveMappingBtn.addEventListener('click', function () {
        const payload = collectMapping();
        document.getElementById('mappingStatus2').textContent = 'Saving...';

        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'save_mapping', mapping_json: JSON.stringify(payload) })
        }).then(r => r.json()).then(res => {
            if (res.ok) {
                document.getElementById('mappingStatus2').textContent = '✅ Saved — reloading...';
                setTimeout(() => location.reload(), 700);
            } else {
                document.getElementById('mappingStatus2').textContent = '❌ Error: ' + (res.message || 'Unknown');
                alert('Save failed: ' + (res.message || 'Unknown'));
            }
        }).catch(err => {
            document.getElementById('mappingStatus2').textContent = '❌ Network error';
            alert('Network error: ' + err.message);
        });
    });

    // Rematch (reload)
    rematchBtn.addEventListener('click', function (e) {
        e.preventDefault();
        location.reload();
    });
}

// Utility Functions
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

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    initMappingUI();
    updateThemeToggle();
});