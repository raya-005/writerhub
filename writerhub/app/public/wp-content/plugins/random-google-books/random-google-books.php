<?php
/*
Plugin Name: Random Google Books List
Description: Fetches and displays 100 random books from the Google Books API using a shortcode.
Version: 1.0
Author: Raya Anjum
*/

function rgb_fetch_latest_books($total_books = 40, $query = '') {
    $api_key = 'AIzaSyCrVJc48he14On6HY1tmRKlEy49byQF1MA';
    
    // Default if nothing searched
    if (empty($query)) {
        $query = 'subject:fiction 2024';
    }

    $collected_books = [];
    $collected_ids   = [];
    $chunk           = 40; // API max
    $startIndex      = 0;

    while (count($collected_books) < $total_books && $startIndex < 80) {
        $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query) .
            "&orderBy=newest&maxResults=" . $chunk .
            "&startIndex=" . $startIndex .
            "&key=" . $api_key;

        $response = wp_remote_get($url);
        if (is_wp_error($response)) break;
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['items'])) {
            foreach ($data['items'] as $book) {
                $book_id = $book['id'];
                if (!in_array($book_id, $collected_ids)) {
                    $collected_ids[] = $book_id;
                    $collected_books[] = $book;
                    if (count($collected_books) == $total_books) break 2;
                }
            }
        } else {
            break;
        }
        $startIndex += $chunk;
    }
    return $collected_books;
}


function rgb_display_books_shortcode($atts) {
    $page = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
    $per_page = 8; 
    $total_books = 40;
    $search_query = isset($_GET['booksearch']) ? sanitize_text_field($_GET['booksearch']) : '';
$books = rgb_fetch_latest_books($total_books, $search_query);





    // Pagination math
    $total_pages = ceil(count($books) / $per_page);
    $page = min($page, $total_pages); // Prevent overflow
    $offset = ($page - 1) * $per_page;
    $page_books = array_slice($books, $offset, $per_page);

    $output = '<style>
    .book-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        padding: 20px;
    }
    .book-card-link {
        color: inherit;
        text-decoration: none;
    }
    .book-card {
        background: linear-gradient(160deg, #ffffff, #f9fafc);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 18px;
        text-align: center;
        cursor: pointer;
    }
    .book-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0,0,0,0.18);
    }
    .book-card img, 
    .book-card .no-image {
        width: 140px;
        height: 200px;
        object-fit: cover;
        margin-bottom: 14px;
        border-radius: 8px;
        background: #f0f0f0;
    }
    .no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 0.9em;
    }
    .book-card-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: #222;
        margin-bottom: 6px;
        line-height: 1.3em;
        max-height: 2.6em;
        overflow: hidden;
    }
    .book-card-author {
        font-size: 0.9rem;
        font-weight: 500;
        color: #555;
        margin-bottom: 10px;
    }
    .book-card-desc {
        font-size: 0.85rem;
        line-height: 1.4em;
        color: #666;
        overflow: hidden;
        max-height: 4.2em;
    }
    .book-card img:hover {
        transform: scale(1.05);
        transition: transform 0.3s ease;
    }
    .book-pagination {
        margin: 18px auto;
        text-align: center;
    }
    .book-pagination a, .book-pagination span.current-page {
        display: inline-block;
        padding: 6px 13px;
        margin: 0 2px;
        border-radius: 5px;
        font-weight: 600;
        background: #f3f5fa;
        color: #2065d1;
        text-decoration: none;
        transition: background 0.18s, color 0.18s;
    }
    .book-pagination a:hover { background: #e2e6ef; color: #183c74;}
    .book-pagination .current-page { background: #2065d1; color: #fff; }
    </style>';

    if (isset($_GET['s']) && !empty($_GET['s'])) {
    $output .= '<h2>Search results for: <em>' . esc_html($_GET['s']) . '</em></h2>';
}


    $output .= '<div class="book-cards-container">';
    foreach ($page_books as $book) {
    $book_id = esc_attr($book['id'] ?? '');
    $title = esc_html($book['volumeInfo']['title'] ?? 'Unknown Title');
    $author = esc_html($book['volumeInfo']['authors'][0] ?? 'None');
    $description = esc_html(mb_substr($book['volumeInfo']['description'] ?? 'No description available.', 0, 120)) . '...';
    $thumbnail = $book['volumeInfo']['imageLinks']['thumbnail'] ?? '';
    $book_url = "https://books.google.com/books?id=" . $book_id;

    $thumbnail_html = $thumbnail
        ? '<a href="'.$book_url.'" target="_blank" rel="noopener noreferrer">
                <img class="book-card-thumb" src="' . esc_url($thumbnail) . '" alt="'. $title .'" loading="lazy">
           </a>'
        : '<div style="width:128px;height:192px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;">No Image</div>';

    $output .= '
        <div class="book-card">
            ' . $thumbnail_html . '
            <div class="book-card-title">' . $title . '</div>
            <div class="book-card-author">' . $author . '</div>
            <div class="book-card-desc">' . $description . '</div>
        </div>
    ';
}

    $output .= "</div>";

    // Pagination Links
    if ($total_pages > 1) {
    $current_url = (is_ssl() ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $base_url = remove_query_arg('pg', $current_url);
    $output .= '<div class="book-pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $page_url = add_query_arg('pg', $i, $base_url);
        if ($i == $page) {
            $output .= '<span class="current-page">'.$i.'</span> ';
        } else {
            $output .= '<a href="' . esc_url($page_url) . '">' . $i . '</a> ';
        }
    }
    $output .= "</div>";
}

    return $output;
}


add_shortcode('random_books_list', 'rgb_display_books_shortcode');

add_filter('get_search_form', function($form) {
    $form = str_replace('action="' . esc_url(home_url('/')) . '"', 'action="' . esc_url(home_url('/books/')) . '"', $form);
    $form = str_replace('name="s"', 'name="booksearch"', $form);
    return $form;
});

