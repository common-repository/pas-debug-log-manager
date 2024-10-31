jQuery(document).ready(function($) {
    var refreshInterval;
    var refreshStatus = $('#refresh-status');
    var autoRefreshCheckbox = $('#auto-refresh');
    var refreshIntervalSelect = $('#refresh-interval');
    var lineCountSelect = $('#line-count');

    autoRefreshCheckbox.change(function() {
        if ($(this).is(':checked')) {
            refreshIntervalSelect.prop('disabled', false);
            startAutoRefresh();
        } else {
            refreshIntervalSelect.prop('disabled', true);
            stopAutoRefresh();
        }
    });

    refreshIntervalSelect.change(function() {
        if (autoRefreshCheckbox.is(':checked')) {
            stopAutoRefresh();
            startAutoRefresh();
        }
    });

    lineCountSelect.change(function() {
        refreshLog();
    });

    function startAutoRefresh() {
        var interval = parseInt(refreshIntervalSelect.val());
        var intervalText = refreshIntervalSelect.find('option:selected').text();
        refreshStatus.text('(Refreshing every ' + intervalText + ')');
        refreshInterval = setInterval(refreshLog, interval);
    }

    function stopAutoRefresh() {
        refreshStatus.text('');
        clearInterval(refreshInterval);
    }

    function refreshLog() {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'pas_dlm_refresh_debug_log',
                line_count: lineCountSelect.val()
            },
            success: function(response) {
                $('#debug-log-content').html(response);
            }
        });
    }
});