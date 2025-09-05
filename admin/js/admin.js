jQuery(document).ready(function($) {
    
    // Test connection button
    $('#test-connection').on('click', function() {
        var $button = $(this);
        var $status = $('#connection-status');
        
        $button.prop('disabled', true).text(whatjobsFeeder.strings.testingConnection);
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: whatjobsFeeder.ajaxUrl,
            type: 'POST',
            data: {
                action: 'whatjobs_feeder_test_connection',
                nonce: whatjobsFeeder.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(whatjobsFeeder.strings.connectionSuccess);
                } else {
                    $status.addClass('error').text(whatjobsFeeder.strings.connectionFailed + ' ' + response.data);
                }
            },
            error: function() {
                $status.addClass('error').text(whatjobsFeeder.strings.connectionFailed + ' Network error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Testar Conexão');
            }
        });
    });
    
    // Run feed button
    $(document).on('click', '.run-feed', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var feedId = $link.data('feed-id');
        var originalText = $link.text();
        
        $link.text(whatjobsFeeder.strings.runningFeed);
        
        $.ajax({
            url: whatjobsFeeder.ajaxUrl,
            type: 'POST',
            data: {
                action: 'whatjobs_feeder_run_feed',
                feed_id: feedId,
                nonce: whatjobsFeeder.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(whatjobsFeeder.strings.feedCompleted + '\n' + response.data.message);
                    location.reload();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function() {
                alert('Erro de rede ao executar o feed.');
            },
            complete: function() {
                $link.text(originalText);
            }
        });
    });
    
    // Pause/Activate feed buttons
    $(document).on('click', '.pause-feed, .activate-feed', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var feedId = $link.data('feed-id');
        var action = $link.hasClass('pause-feed') ? 'pause' : 'activate';
        
        $.ajax({
            url: whatjobsFeeder.ajaxUrl,
            type: 'POST',
            data: {
                action: 'whatjobs_feeder_toggle_feed',
                feed_id: feedId,
                feed_action: action,
                nonce: whatjobsFeeder.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erro: ' + response.data);
                }
            },
            error: function() {
                alert('Erro de rede.');
            }
        });
    });
    
    // Confirm delete actions
    $(document).on('click', 'a[href*="action=delete"]', function(e) {
        if (!confirm('Tem certeza que deseja excluir este feed? Esta ação não pode ser desfeita.')) {
            e.preventDefault();
        }
    });
    
    // Auto-save form data in localStorage
    var formSelector = 'form[action*="whatjobs-feeder"]';
    
    // Load saved data
    if (localStorage.getItem('whatjobs_feeder_form_data')) {
        try {
            var savedData = JSON.parse(localStorage.getItem('whatjobs_feeder_form_data'));
            $.each(savedData, function(name, value) {
                var $field = $('[name="' + name + '"]');
                if ($field.length) {
                    if ($field.is('select')) {
                        $field.val(value);
                    } else if ($field.is('input[type="text"], input[type="number"], textarea')) {
                        $field.val(value);
                    }
                }
            });
        } catch (e) {
            // Invalid JSON, ignore
        }
    }
    
    // Save form data on change
    $(document).on('change input', formSelector + ' input, ' + formSelector + ' select, ' + formSelector + ' textarea', function() {
        var formData = {};
        $(formSelector).find('input, select, textarea').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            if (name && name !== '_wpnonce' && name !== 'action') {
                formData[name] = $field.val();
            }
        });
        localStorage.setItem('whatjobs_feeder_form_data', JSON.stringify(formData));
    });
    
    // Clear saved data on successful form submission
    $(formSelector).on('submit', function() {
        setTimeout(function() {
            localStorage.removeItem('whatjobs_feeder_form_data');
        }, 1000);
    });
});