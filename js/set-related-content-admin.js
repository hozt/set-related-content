jQuery(document).ready(function($) {
    $('.set-related-content').each(function() {
        var container = $(this);
        // Fix content type extraction
        var contentType = container.attr('id').replace('set-related-', '');
        contentType = contentType.replace(/s$/, ''); // Only remove trailing 's'

        // Validate content type
        if (!['post', 'event'].includes(contentType)) {
            contentType = 'post'; // Default to post if invalid
        }

        var relatedContentList = container.find('.related-content-list');
        var relatedContentSearch = container.find('input[type="text"]');

        // Add hidden input field to store related content IDs
        var hiddenInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'related_' + contentType + 's')
            .attr('value', '');

        container.append(hiddenInput);

        // Update hidden input when list changes
        function updateHiddenInput() {
            var ids = relatedContentList.find('li').map(function() {
                return $(this).data('id');
            }).get();
            hiddenInput.val(JSON.stringify(ids));
        }

        relatedContentList.sortable({
            update: function(event, ui) {
                var sortedIds = relatedContentList.find('li').map(function() {
                    return $(this).data('id');
                }).get();

                $.ajax({
                    url: setRelatedContent.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_sorted_related_content',
                        content_ids: sortedIds,
                        post_parent_id: $('#post_ID').val(),
                        content_type: contentType,
                        set_related_content_nonce: $('#set_related_content_nonce').val()
                    },
                    success: function(response) {
                        updateHiddenInput();
                        console.log('Sorted related content saved:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving sorted related content:', error);
                    }
                });
            }
        });

        relatedContentSearch.autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: setRelatedContent.ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'search_content',
                        search: request.term,
                        content_type: contentType,
                        set_related_content_nonce: $('#set_related_content_nonce').val()
                    },
                    success: function(data) {
                        response($.map(data, function(item) {
                            return {
                                label: item.title,
                                value: item.id
                            };
                        }));
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                var listItem = $('<li data-id="' + ui.item.value + '">' + ui.item.label + ' <button style="font-size:0.8em;" class="remove" type="button">Remove</button></li>');
                relatedContentList.append(listItem);
                updateHiddenInput();

                $.ajax({
                    url: setRelatedContent.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_related_content',
                        content_id: ui.item.value,
                        post_parent_id: $('#post_ID').val(),
                        content_type: contentType,
                        set_related_content_nonce: $('#set_related_content_nonce').val()
                    },
                    success: function(response) {
                        console.log('Related content saved:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving related content:', error);
                        listItem.remove();
                        updateHiddenInput();
                    }
                });

                relatedContentSearch.val('');
                return false;
            }
        });

        relatedContentList.on('click', '.remove', function() {
            var listItem = $(this).parent();
            var contentId = listItem.data('id');

            $.ajax({
                url: setRelatedContent.ajaxurl,
                type: 'POST',
                data: {
                    action: 'remove_related_content',
                    content_id: contentId,
                    post_parent_id: $('#post_ID').val(),
                    content_type: contentType,
                    set_related_content_nonce: $('#set_related_content_nonce').val()
                },
                success: function(response) {
                    listItem.remove();
                    updateHiddenInput();
                    console.log('Related content removed:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error removing related content:', error);
                }
            });
        });

        // Initialize hidden input with current values
        updateHiddenInput();
    });
});
