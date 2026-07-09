(function(window, $) {
    'use strict';

    if (!$) return;

    function updateButton($button, data) {
        var favorited = !!data.favorited;
        $button
            .toggleClass('is-favorited', favorited)
            .attr('data-favorited', favorited ? '1' : '0')
            .attr('aria-pressed', favorited ? 'true' : 'false')
            .attr('title', favorited ? 'Remove from favorites' : 'Add to favorites');

        $button.find('i.fa-heart')
            .toggleClass('fas', favorited)
            .toggleClass('far', !favorited);

        $button.find('.homestead-favorite-count').text(data.count);
    }

    $(document).on('click', '.homestead-favorite-button', function(event) {
        event.preventDefault();
        event.stopPropagation();

        var $button = $(this);
        if ($button.prop('disabled')) return;

        var refType = $button.data('ref-type');
        var refId = $button.data('ref-id');
        if (!refType || !refId) return;

        $button.prop('disabled', true);

        $.ajax({
            method: 'POST',
            url: '/favorites/toggle',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                ref_type: refType,
                ref_id: refId,
            },
        }).done(function(data) {
            updateButton($button, data);
        }).fail(function(xhr) {
            var message = 'Unable to update favorite.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                message = xhr.responseJSON.error;
            }
            alert(message);
        }).always(function() {
            $button.prop('disabled', false);
        });
    });
})(window, window.jQuery);
