jQuery(document).ready(function($) {
    var relatedPostsList = $('#related-posts-list');
    var relatedPostSearch = $('#related-post-search');

    relatedPostsList.sortable({
        update: function(event, ui) {
            // Get the new order of related post IDs after sorting
            var sortedIds = relatedPostsList.find('li').map(function() {
                return $(this).data('id');
            }).get();

            // Save the new order via AJAX
            $.ajax({
                url: setRelatedContent.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_sorted_related_posts',
                    post_ids: sortedIds, // The sorted array of post IDs
                    post_parent_id: $('#post_ID').val(), // The current post ID
                    nonce: $('#set_related_content_nonce').val() // Nonce for security
                },
                success: function(response) {
                    console.log('Sorted related posts saved:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving sorted related posts:', error);
                }
            });
        }
    });

    relatedPostSearch.autocomplete({
        source: function(request, response) {
            $.ajax({
                url: setRelatedContent.ajaxurl,
                dataType: 'json',
                data: {
                    action: 'search_posts',
                    search: request.term
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
            var listItem = $('<li data-id="' + ui.item.value + '">' + ui.item.label + ' <span class="remove">Remove</span></li>');
            relatedPostsList.append(listItem);
            relatedPostSearch.val('');

            // Save related post immediately
            $.ajax({
                url: setRelatedContent.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_related_post',
                    post_id: ui.item.value, // The selected post ID
                    post_parent_id: $('#post_ID').val(), // The current post ID
                    nonce: $('#set_related_content_nonce').val() // Nonce for security
                },
                success: function(response) {
                    console.log('Related post saved:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving related post:', error);
                }
            });

            return false;
        }
    });

    relatedPostsList.on('click', '.remove', function() {
        var listItem = $(this).parent();
        var postId = listItem.data('id');

        // Remove related post immediately
        $.ajax({
            url: setRelatedContent.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_related_post',
                post_id: postId, // The related post ID to remove
                post_parent_id: $('#post_ID').val(), // The current post ID
                nonce: $('#set_related_content_nonce').val() // Nonce for security
            },
            success: function(response) {
                if (response.success) {
                    listItem.remove(); // Remove the item from the list on success
                    console.log('Related post removed:', response.data);
                } else {
                    console.error('Error removing related post:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error removing related post:', error);
            }
        });
    });
});

