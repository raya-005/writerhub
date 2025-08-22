<?php
/*
Plugin Name: Random Google Books List
Description: Fetches and displays 100 random books from the Google Books API using a shortcode.
Version: 1.0
Author: Raya Anjum
*/

function rgb_fetch_latest_books($total_books = 40, $query = 'subject:fiction 2024') {
    $api_key = 'AIzaSyCrVJc48he14On6HY1tmRKlEy49byQF1MA';
    $collected_books = [];
    $collected_ids = [];
    $chunk = 40; // Google Books API max is 40
    $startIndex = 0;
    // Loop until you collect requested number or run out of results
    while (count($collected_books) < $total_books && $startIndex < 80) { // 2 pages 0/40
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
            break; // no more items
        }
        $startIndex += $chunk;
    }
    return $collected_books;
}



function rgb_display_books_shortcode($atts) {
    $books = rgb_fetch_latest_books(40);

     $output = '<script>
window.randomBooksData = ' . json_encode($books) . ';
console.log("Random Books Data:", window.randomBooksData);
</script>';

$output .= '
    <style>
    .book-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        padding: 20px;
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

    /* Add a nice subtle hover effect on image */
    .book-card img:hover {
        transform: scale(1.05);
        transition: transform 0.3s ease;
    }
    </style>


    <div class="book-cards-container">
    ';

    foreach ($books as $book) {
    $book_id = esc_attr($book['id'] ?? '');
    $title = esc_html($book['volumeInfo']['title'] ?? 'Unknown Title');
    $author = esc_html($book['volumeInfo']['authors'][0] ?? 'none');
    $description = esc_html(mb_substr($book['volumeInfo']['description'] ?? 'No description available.', 0, 120)) . '...';
    $thumbnail = $book['volumeInfo']['imageLinks']['thumbnail'] ?? '';
    $thumbnail_html = $thumbnail
        ? '<img class="book-card-thumb" src="' . esc_url($thumbnail) . '" alt="'. $title .'" loading="lazy">'
        : '<div style="width:128px;height:192px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;">No Image</div>';
    $book_url = "https://books.google.com/books?id=" . $book_id;

    $output .= '
        <a class="book-card-link" href="'.$book_url.'" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">
            <div class="book-card">
                ' . $thumbnail_html . '
                <div class="book-card-title">' . $title . '</div>
                <div class="book-card-author">' . $author . '</div>
                <div class="book-card-desc">' . $description . '</div>
            </div>
        </a>
    ';
}

    $output .= "</div>";
    return $output;
    

    }



add_shortcode('random_books_list', 'rgb_display_books_shortcode');
