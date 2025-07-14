// Define globally accessible function before or after the jQuery ready block
window.fetchOrganisations = function() {
    jQuery('#workbooks_sync_employers').trigger('click');
}

jQuery(document).ready(function ($) {
    var debug = true; // set to false in production

    function log(message, data) {
        if (debug && console) {
            console.log(message, data || '');
        }
    }

    if (typeof workbooks_ajax === 'undefined') {
        console.error('ERROR: workbooks_ajax object is not defined');
        $('#employers-sync-status').text('Configuration error: AJAX settings not found');
        return;
    }

    var allEmployers = [];
    var currentOffset = 0;
    var employersPerPage = 20;
    var isLoading = false;
    var searchTimer;

    $('#workbooks_load_employers').on('click', function () {
        loadInitialEmployers();
    });

    function loadInitialEmployers() {
        $('#employers-table-body').html('<tr><td colspan="4">Loading employers...</td></tr>');
        $('#employers-table-container').show();
        $('#employers-search-container').show();
        currentOffset = 0;
        allEmployers = [];
        loadMoreEmployers();
    }

    function loadMoreEmployers() {
        if (isLoading) return;

        isLoading = true;
        $('#load-more-employers').prop('disabled', true).text('Loading...');

        var ajaxData = {
            action: 'fetch_workbooks_employers_paged',
            nonce: workbooks_ajax.nonce,
            offset: currentOffset,
            limit: employersPerPage,
            search: $('#employer-search').val()
        };

        $.ajax({
            url: workbooks_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                isLoading = false;
                log('Employer fetch success', response);

                if (response && response.success) {
                    var employers = response.data.employers || [];
                    var total = response.data.total || 0;

                    if (currentOffset === 0) {
                        allEmployers = employers;
                        $('#employers-table-body').empty();
                    } else {
                        allEmployers = allEmployers.concat(employers);
                    }

                    if (employers.length === 0 && currentOffset === 0) {
                        $('#employers-table-body').html('<tr><td colspan="4">No employers found.</td></tr>');
                        $('#employers-pagination').hide();
                    } else {
                        var rowsHtml = '';
                        $.each(employers, function (index, employer) {
                            rowsHtml += '<tr>' +
                                '<td>' + (employer.id || '') + '</td>' +
                                '<td>' + (employer.name || '') + '</td>' +
                                '<td>' + (employer.last_updated || 'Unknown') + '</td>' +
                                '<td><button type="button" class="button resync-employer" data-id="' + employer.id + '">Resync</button></td>' +
                                '</tr>';
                        });
                        $('#employers-table-body').append(rowsHtml);

                        currentOffset += employers.length;
                        $('#employer-count').text(total);

                        if (currentOffset < total) {
                            $('#employers-pagination').show();
                            $('#load-more-employers').prop('disabled', false).text('Load More');
                        } else {
                            $('#employers-pagination').hide();
                        }
                    }
                } else {
                    var errorMsg = response?.data || 'Unknown error';
                    log('Employer fetch error', errorMsg);
                    $('#employers-table-body').html('<tr><td colspan="4">Error: ' + errorMsg + '</td></tr>');
                    $('#load-more-employers').prop('disabled', false).text('Try Again');
                    $('#employers-pagination').hide();
                }
            },
            error: function (xhr, status, error) {
                isLoading = false;
                log('Employer fetch AJAX error', { xhr, status, error });
                console.error('AJAX Response Text:', xhr.responseText);
                $('#employers-table-body').html('<tr><td colspan="4">Failed to load employers: ' + status + ' ' + error + '</td></tr>');
                $('#load-more-employers').prop('disabled', false).text('Try Again');
            }
        });
    }

    $('#load-more-employers').on('click', loadMoreEmployers);

    $('#employer-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentOffset = 0;
            $('#employers-table-body').empty();
            loadMoreEmployers();
        }, 500);
    });

    $('#employer-search-btn').on('click', function () {
        clearTimeout(searchTimer);
        currentOffset = 0;
        $('#employers-table-body').empty();
        loadMoreEmployers();
    });

    $('#employer-reset-btn').on('click', function () {
        $('#employer-search').val('');
        currentOffset = 0;
        $('#employers-table-body').empty();
        loadMoreEmployers();
    });

    $(document).on('click', '.resync-employer', function () {
        var $button = $(this);
        var employerId = $button.data('id');
        var $row = $button.closest('tr');

        if (!employerId) return alert('Missing employer ID');

        $button.prop('disabled', true).text('Syncing...');

        $.ajax({
            url: workbooks_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'resync_workbooks_employer',
                nonce: workbooks_ajax.nonce,
                employer_id: employerId
            },
            dataType: 'json',
            success: function (response) {
                log('Resync success', response);
                if (response && response.success) {
                    $row.find('td:nth-child(3)').text(response.data.last_updated || 'Just updated');
                    $button.text('Done!');
                    setTimeout(() => {
                        $button.prop('disabled', false).text('Resync');
                    }, 1500);
                } else {
                    var errorMsg = response?.data || 'Unknown error';
                    $button.text('Failed!');
                    alert('Failed to resync: ' + errorMsg);
                    setTimeout(() => {
                        $button.prop('disabled', false).text('Resync');
                    }, 1500);
                }
            },
            error: function (xhr, status, error) {
                console.error('Resync error response:', xhr.responseText);
                $button.text('Failed!');
                alert('Request failed: ' + status + ' ' + error);
                setTimeout(() => {
                    $button.prop('disabled', false).text('Resync');
                }, 1500);
            }
        });
    });

    $('#workbooks_sync_employers').on('click', function () {
        var $button = $(this);
        $button.prop('disabled', true);
        $('#employers-sync-status').text('Syncing...');
        $('#employers-sync-progress').show();
        $('#employers-progress-text').text('Starting...');
        $('#employers-progress-bar').val(0);

        var allOrganisations = [];
        var start = 0;
        var batchSize = 100;

        function fetchBatch() {
            $.ajax({
                url: workbooks_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fetch_workbooks_organisations_batch',
                    nonce: workbooks_ajax.nonce,
                    start: start,
                    batch_size: batchSize
                },
                dataType: 'json',
                timeout: 30000,
                success: function (response) {
                    log('Batch fetch success', response);

                    if (response && response.success) {
                        const batch = response.data.organisations || [];
                        allOrganisations = allOrganisations.concat(batch);
                        const total = response.data.total || allOrganisations.length;
                        const progress = Math.min(((start + batchSize) / total) * 100, 100);
                        $('#employers-progress-bar').val(progress);
                        $('#employers-progress-text').text(`Fetched ${allOrganisations.length} employers...`);

                        if (response.data.has_more) {
                            start += batchSize;
                            setTimeout(fetchBatch, 100);
                        } else {
                            $('#employers-sync-status').text('Sync complete!');
                            $('#employers-progress-text').text(`Synced ${allOrganisations.length} employers.`);
                            $('#employers-progress-bar').val(100);
                            $button.prop('disabled', false);

                            if ($('#employers-table-container').is(':visible')) {
                                loadInitialEmployers();
                            }

                            setTimeout(() => {
                                $('#employers-sync-progress').fadeOut();
                            }, 3000);
                        }
                    } else {
                        const err = response?.data || 'Unknown error';
                        $('#employers-sync-status').text('Error: ' + err);
                        $('#employers-progress-text').text('Sync failed.');
                        $button.prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Batch AJAX error:', xhr.responseText);
                    $('#employers-sync-status').text(`Error: AJAX request failed - ${status} ${error}`);
                    $('#employers-progress-text').text('Sync failed.');
                    $button.prop('disabled', false);
                }
            });
        }

        fetchBatch();
    });

    log('Employer sync JS initialized');
});
