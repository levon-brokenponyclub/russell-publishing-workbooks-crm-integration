<?php
/**
 * Admin page content for JSON Import functionality
 * Provides interface for importing organization data from JSON files
 *
 * @package DTR/WorkbooksIntegration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access.');
}

global $wpdb;

// Handle form submission
$message = '';
$message_type = '';

if (isset($_POST['submit_import']) && wp_verify_nonce($_POST['_wpnonce'], 'dtr_json_import')) {
    $json_file_path = sanitize_text_field($_POST['json_file_path']);
    
    if (empty($json_file_path)) {
        $message = 'Please enter the path to your JSON file.';
        $message_type = 'error';
    } else {
        // Process the import
        $result = dtr_process_json_import($json_file_path);
        
        if ($result['success']) {
            $message = sprintf(
                'Import completed successfully! Total: %d, Imported: %d, Errors: %d',
                $result['total'],
                $result['imported'],
                $result['errors']
            );
            $message_type = 'success';
        } else {
            $message = 'Import failed: ' . $result['message'];
            $message_type = 'error';
        }
    }
}

// Check if wp_workbooks_employers table exists
$table_name = $wpdb->prefix . 'workbooks_employers';
$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);

?>

<div class="wrap dtr-json-import">
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Import Organizations from JSON</h2>
        <p>This tool allows you to import organization data from a JSON file into the <code>wp_workbooks_employers</code> table.</p>
        
        <?php if (!$table_exists): ?>
            <div class="notice notice-warning">
                <p><strong>Database Table Missing:</strong> The <code><?php echo esc_html($table_name); ?></code> table does not exist. It will be created automatically during the import process.</p>
            </div>
        <?php else: ?>
            <?php
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            ?>
            <div class="notice notice-info">
                <p><strong>Current Records:</strong> The <code><?php echo esc_html($table_name); ?></code> table currently contains <?php echo intval($count); ?> records.</p>
            </div>
        <?php endif; ?>

        <form id="json-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('dtr_json_import'); ?>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="json_file_upload">JSON File</label>
                        </th>
                        <td>
                            <input type="file" 
                                   name="json_file_upload" 
                                   id="json_file_upload" 
                                   accept=".json,application/json"
                                   required>
                            <p class="description">
                                Select your JSON file to upload and import. Maximum file size: <?php echo wp_max_upload_size(); ?> bytes.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Progress Bar -->
            <div id="import-progress" style="display: none;">
                <h3>Import Progress</h3>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span id="progress-percentage">0%</span>
                        <span id="progress-status">Ready to import...</span>
                    </div>
                    <div id="progress-details"></div>
                </div>
            </div>

            <div class="json-format-info">
                <h3>Expected JSON Format</h3>
                <p>Your JSON file should contain an array of organization objects with the following structure:</p>
                <pre><code>[
  {
    "id": "123",
    "name": "Company Name"
  },
  {
    "id": "124",
    "name": "Another Company"
  },
  ...
]</code></pre>
                <p><strong>Required fields:</strong> <code>id</code> and <code>name</code></p>
                <p><strong>Note:</strong> Only the ID and name will be imported. All other fields will be ignored.</p>
            </div>

            <p class="submit">
                <input type="submit" 
                       name="submit_import" 
                       id="submit_import" 
                       class="button-primary" 
                       value="Upload and Import JSON Data">
                <button type="button" 
                        id="cancel_import" 
                        class="button" 
                        style="display: none;">Cancel Import</button>
                <span class="spinner" id="import-spinner"></span>
            </p>
        </form>
    </div>

    <?php if ($table_exists): ?>
    <div class="card">
        <h3>Current Employers Data</h3>
        <?php
        $recent_employers = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
        if ($recent_employers): ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Workbooks ID</th>
                        <th>Name</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_employers as $employer): ?>
                    <tr>
                        <td><?php echo intval($employer->id); ?></td>
                        <td><?php echo intval($employer->workbooks_id); ?></td>
                        <td><?php echo esc_html($employer->name); ?></td>
                        <td><?php echo esc_html($employer->created_at); ?></td>
                        <td><?php echo esc_html($employer->updated_at); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($count > 10): ?>
                <p><em>Showing 10 most recent records out of <?php echo intval($count); ?> total.</em></p>
            <?php endif; ?>
        <?php else: ?>
            <p>No employers found in the database.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.dtr-json-import .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.dtr-json-import .json-format-info {
    background: #f9f9f9;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.dtr-json-import .json-format-info pre {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.4;
}

.status-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

#import-spinner {
    float: none;
    margin-left: 10px;
}

.form-table th {
    width: 150px;
}

.notice {
    margin: 15px 0;
}

/* Progress Bar Styles */
.progress-container {
    margin: 20px 0;
}

.progress-bar {
    width: 100%;
    height: 25px;
    background-color: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ccd0d4;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa 0%, #005a87 100%);
    width: 0%;
    transition: width 0.3s ease;
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, 
        rgba(255,255,255,0.2) 25%, 
        transparent 25%, 
        transparent 50%, 
        rgba(255,255,255,0.2) 50%, 
        rgba(255,255,255,0.2) 75%, 
        transparent 75%, 
        transparent);
    background-size: 20px 20px;
    animation: progress-stripes 1s linear infinite;
}

@keyframes progress-stripes {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 20px 0;
    }
}

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 10px;
}

#progress-percentage {
    font-weight: bold;
    color: #0073aa;
}

#progress-status {
    color: #666;
}

#progress-details {
    background: #f9f9f9;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 10px;
    font-family: monospace;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-line;
}

#import-progress.error .progress-fill {
    background: linear-gradient(90deg, #dc3232 0%, #a02020 100%);
}

#import-progress.success .progress-fill {
    background: linear-gradient(90deg, #46b450 0%, #368a3c 100%);
}
</style>

<script>
jQuery(document).ready(function($) {
    var importInProgress = false;
    var importXHR = null;
    
    $('#json-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        if (importInProgress) {
            return false;
        }
        
        var fileInput = $('#json_file_upload')[0];
        var file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a JSON file to upload.');
            return false;
        }
        
        if (file.type !== 'application/json' && !file.name.toLowerCase().endsWith('.json')) {
            alert('Please select a valid JSON file.');
            return false;
        }
        
        startImport(file);
    });
    
    $('#cancel_import').on('click', function() {
        if (importXHR) {
            importXHR.abort();
        }
        resetImportUI();
    });
    
    function startImport(file) {
        importInProgress = true;
        
        // Show progress section
        $('#import-progress').show();
        $('#submit_import').prop('disabled', true);
        $('#cancel_import').show();
        $('#import-spinner').addClass('is-active');
        
        // Reset progress
        updateProgress(0, 'Uploading file...');
        
        // Create FormData
        var formData = new FormData();
        formData.append('action', 'dtr_json_import_ajax');
        formData.append('nonce', '<?php echo wp_create_nonce('dtr_json_import_ajax'); ?>');
        formData.append('json_file', file);
        
        // Start upload and import
        importXHR = $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                
                // Upload progress
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 30); // Upload is 30% of total progress
                        updateProgress(percentComplete, 'Uploading file...');
                    }
                }, false);
                
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // File uploaded, now process import
                    processImport(response.data.file_path, response.data.total_records);
                } else {
                    showError('Upload failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                if (status !== 'abort') {
                    showError('Upload error: ' + error);
                }
            }
        });
    }
    
    function processImport(filePath, totalRecords) {
        updateProgress(30, 'Processing import...');
        
        // Process import in batches
        var batchSize = 50;
        var totalBatches = Math.ceil(totalRecords / batchSize);
        var currentBatch = 0;
        var imported = 0;
        var errors = 0;
        
        function processBatch() {
            if (currentBatch >= totalBatches) {
                // Import complete
                completeImport(imported, errors, totalRecords);
                return;
            }
            
            var progressPercent = 30 + Math.round((currentBatch / totalBatches) * 70);
            updateProgress(progressPercent, 'Processing batch ' + (currentBatch + 1) + ' of ' + totalBatches + '...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dtr_json_import_batch',
                    nonce: '<?php echo wp_create_nonce('dtr_json_import_batch'); ?>',
                    file_path: filePath,
                    batch: currentBatch,
                    batch_size: batchSize
                },
                success: function(response) {
                    if (response.success) {
                        imported += response.data.imported;
                        errors += response.data.errors;
                        
                        // Update details
                        addProgressDetail('Batch ' + (currentBatch + 1) + ': ' + response.data.imported + ' imported, ' + response.data.errors + ' errors');
                        
                        currentBatch++;
                        setTimeout(processBatch, 100); // Small delay between batches
                    } else {
                        showError('Batch processing failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    if (status !== 'abort') {
                        showError('Batch processing error: ' + error);
                    }
                }
            });
        }
        
        processBatch();
    }
    
    function completeImport(imported, errors, total) {
        updateProgress(100, 'Import completed!');
        $('#import-progress').addClass('success');
        
        var summary = 'Import Summary:\n';
        summary += 'Total records: ' + total + '\n';
        summary += 'Successfully imported: ' + imported + '\n';
        summary += 'Errors: ' + errors + '\n';
        
        addProgressDetail(summary);
        
        // Show success message
        var $notice = $('<div class="notice notice-success is-dismissible"><p>Import completed successfully! ' + imported + ' records imported, ' + errors + ' errors.</p></div>');
        $('.wrap').prepend($notice);
        
        // Reload the current data table
        setTimeout(function() {
            location.reload();
        }, 3000);
        
        resetImportUI();
    }
    
    function showError(message) {
        $('#import-progress').addClass('error');
        updateProgress(0, 'Import failed');
        addProgressDetail('ERROR: ' + message);
        
        var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').prepend($notice);
        
        resetImportUI();
    }
    
    function updateProgress(percent, status) {
        $('#progress-fill').css('width', percent + '%');
        $('#progress-percentage').text(percent + '%');
        $('#progress-status').text(status);
    }
    
    function addProgressDetail(detail) {
        var $details = $('#progress-details');
        $details.append(detail + '\n');
        $details.scrollTop($details[0].scrollHeight);
    }
    
    function resetImportUI() {
        importInProgress = false;
        importXHR = null;
        
        $('#submit_import').prop('disabled', false);
        $('#cancel_import').hide();
        $('#import-spinner').removeClass('is-active');
        
        setTimeout(function() {
            $('#import-progress').removeClass('error success');
        }, 5000);
    }
});
</script>

<?php

/**
 * Process JSON import
 *
 * @param string $json_file_path Path to JSON file
 * @return array Result with success status and statistics
 */
function dtr_process_json_import($json_file_path) {
    global $wpdb;
    
    try {
        // Check if file exists
        if (!file_exists($json_file_path)) {
            return [
                'success' => false,
                'message' => 'JSON file not found: ' . $json_file_path
            ];
        }
        
        // Read and decode JSON
        $json_content = file_get_contents($json_file_path);
        if ($json_content === false) {
            return [
                'success' => false,
                'message' => 'Could not read JSON file'
            ];
        }
        
        $organizations = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON format: ' . json_last_error_msg()
            ];
        }
        
        if (!is_array($organizations)) {
            return [
                'success' => false,
                'message' => 'JSON must contain an array of organizations'
            ];
        }
        
        // Create table if it doesn't exist
        $table_name = $wpdb->prefix . 'workbooks_employers';
        dtr_create_employers_table($table_name);
        
        $imported = 0;
        $errors = 0;
        $total = count($organizations);
        
        foreach ($organizations as $org) {
            // Validate required fields
            if (empty($org['id']) || empty($org['name'])) {
                $errors++;
                error_log('DTR JSON Import: Skipping organization - missing id or name: ' . print_r($org, true));
                continue;
            }
            
            // Prepare data for insertion - handle string IDs properly
            $org_id = is_numeric($org['id']) ? intval($org['id']) : 0;
            
            // Skip if ID converts to 0 (invalid)
            if ($org_id <= 0) {
                $errors++;
                error_log('DTR JSON Import: Invalid ID: ' . $org['id']);
                continue;
            }
            
            $data = [
                'id' => $org_id,
                'name' => sanitize_text_field(trim($org['name'])),
                'last_updated' => current_time('mysql')
            ];
            
            // Check for duplicate ID
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE id = %d",
                $data['id']
            ));
            
            if ($existing) {
                // Update existing record
                $result = $wpdb->update(
                    $table_name,
                    ['name' => $data['name'], 'last_updated' => current_time('mysql')],
                    ['id' => $data['id']],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                // Insert new record
                $result = $wpdb->insert(
                    $table_name,
                    $data,
                    ['%d', '%s', '%s']
                );
            }
            
            if ($result !== false) {
                $imported++;
            } else {
                $errors++;
                error_log('DTR JSON Import: Database error for organization: ' . print_r($org, true) . ' Error: ' . $wpdb->last_error);
            }
        }
        
        return [
            'success' => true,
            'total' => $total,
            'imported' => $imported,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Import error: ' . $e->getMessage()
        ];
    }
}

/**
 * Create employers table if it doesn't exist
 *
 * @param string $table_name Table name
 */
function dtr_create_employers_table($table_name) {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if table exists
    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
    
    if (!$table_exists) {
        // Create new table with your existing schema
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } else {
        // Check if required columns exist and add them if missing
        $required_columns = [
            'name' => "varchar(255) NOT NULL DEFAULT ''",
            'last_updated' => "datetime DEFAULT CURRENT_TIMESTAMP"
        ];
        
        foreach ($required_columns as $column => $definition) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column $definition");
                error_log("DTR Import: Added $column column to existing table");
            }
        }
    }
}