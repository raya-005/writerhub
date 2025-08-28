<?php
// Get the book ID from the URL query var
$book_id = get_query_var('book_id');

if (!$book_id) {
    echo '<p>No Book ID provided.</p>';
    return;
}

$api_key = 'AIzaSyCrVJc48he14On6HY1tmRKlEy49byQF1MA'; // Replace with your key
$api_url = 'https://www.googleapis.com/books/v1/volumes/' . urlencode($book_id) . '?key=' . $api_key;

$response = wp_remote_get($api_url);

if (is_wp_error($response)) {
    echo '<p>Could not fetch book information.</p>';
    return;
}

$data = json_decode(wp_remote_retrieve_body($response), true);

if (empty($data['volumeInfo'])) {
    echo '<p>No information available for this book.</p>';
    return;
}

$info = $data['volumeInfo'];

echo "<script>console.log(" . json_encode($info) . ");</script>";

// Safe fields
$title        = esc_html($info['title'] ?? 'Untitled');
$authors      = !empty($info['authors']) ? esc_html(implode(', ', $info['authors'])) : 'Unknown Author';
$description  = !empty($info['description']) ? wp_kses_post($info['description']) : 'No description available.';
$categories   = !empty($info['categories']) ? esc_html(implode(', ', $info['categories'])) : '';
$published    = !empty($info['publishedDate']) ? esc_html($info['publishedDate']) : '';
$pages        = !empty($info['pageCount']) ? intval($info['pageCount']) . ' pages' : '';
$thumbnail = !empty($info['imageLinks']['thumbnail'])
    ? esc_url($info['imageLinks']['thumbnail'])
    : '';

$preview_link = $info['previewLink'] ?? '';
$google_books_url = 'https://books.google.com/books?id=' . esc_attr($book_id);

// echo '<pre>';
// print_r($info['imageLinks'] ?? []);
// echo '</pre>';


?>

<style>
.book-details-container {
    max-width: 900px;
    margin: 50px auto;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    padding: 32px;
    font-family: "Segoe UI", sans-serif;
}

.book-details-header {
    display: flex;
    flex-wrap: wrap;
    gap: 32px;
    align-items: flex-start;
    margin-bottom: 24px;
}

.book-details-img {
    width: 220px;
    height: 320px;
    border-radius: 10px;
    object-fit: cover;
    background: #f2f4f8;
    flex-shrink: 0;
    transition: transform 0.25s ease;
}
.book-details-img:hover {
    transform: scale(1.03);
}

.book-details-content {
    flex: 1;
    min-width: 240px;
}

.book-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 10px;
    color: #1a1a1a;
}

.book-author {
    color: #2466e8;
    font-size: 1.1rem;
    margin-bottom: 12px;
    font-weight: 600;
}

.book-meta {
    font-size: 0.95rem;
    color: #666;
    margin-bottom: 18px;
}
.book-meta span {
    display: inline-block;
    margin-right: 15px;
}

.book-description {
    color: #333;
    font-size: 1rem;
    line-height: 1.6em;
    margin: 24px 0;
}

.read-book-btn {
    display: inline-block;
    background: #2466e8;
    color: #fff;
    border-radius: 8px;
    padding: 12px 26px;
    font-size: 1rem;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(36,102,232,0.3);
    transition: all 0.2s ease;
}
.read-book-btn:hover {
    background: #1855b4;
    box-shadow: 0 6px 16px rgba(36,102,232,0.4);
}
</style>

<div class="book-details-container">
    <div class="book-details-header">
        <?php if ($thumbnail): ?>
            <img class="book-details-img" src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo $title; ?>">
        <?php else: ?>
            <div class="book-details-img" style="display: flex; align-items: center; justify-content: center; color: #888;">No Image</div>
        <?php endif; ?>

        <div class="book-details-content">
            <div class="book-title"><?php echo $title; ?></div>
            <div class="book-author"><?php echo $authors; ?></div>
            <div class="book-meta">
                <?php if ($categories): ?><span><strong>Category:</strong> <?php echo $categories; ?></span><?php endif; ?>
                <?php if ($published): ?><span><strong>Published:</strong> <?php echo $published; ?></span><?php endif; ?>
                <?php if ($pages): ?><span><strong>Pages:</strong> <?php echo $pages; ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="book-description"><?php echo $description; ?></div>

    <a class="read-book-btn" href="<?php echo esc_url($google_books_url); ?>" target="_blank" rel="noopener noreferrer">
        📖 Read It on Google Books
    </a>
</div>
