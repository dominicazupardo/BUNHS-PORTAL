<?php

$query = "SELECT * FROM news ORDER BY created_at DESC LIMIT 4";
$result = $conn->query($query);
$news_posts = [];
while ($row = $result->fetch_assoc()) {
    $news_posts[] = $row;
}

if (count($news_posts) == 0) {
    echo '<div class="col-12"><div class="empty-state"><i class="bi bi-newspaper"></i>No news posts available.</div></div>';
} else {
    foreach ($news_posts as $post) {
        $image   = !empty($post['image']) ? "assets/img/blog/" . $post['image'] : "assets/img/blog/default.webp";
        $date    = date("D, M d", strtotime($post['news_date']));
        $excerpt = mb_substr($post['short_description'], 0, 110) . '…';

        echo '
        <div class="col-xl-3 col-md-6">
          <div class="post-box">
            <div class="post-img">
              <img src="' . $image . '" class="img-fluid" alt="' . htmlspecialchars($post['title']) . '" loading="lazy">
              <span class="post-category-ribbon">' . htmlspecialchars($post['category']) . '</span>
            </div>
            <div class="meta">
              <span class="post-date">' . $date . '</span>
              <span class="post-author"> / ' . htmlspecialchars($post['author']) . '</span>
            </div>
            <h3 class="post-title">' . htmlspecialchars($post['title']) . '</h3>
            <p class="excerpt">' . htmlspecialchars($excerpt) . '</p>
            <div class="interaction-bar" data-post-id="' . $post['id'] . '">
              <button class="interaction-btn like-btn" onclick="toggleLike(' . $post['id'] . ')">
                <i class="far fa-heart"></i>
                <span class="count like-count" id="like-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn comment-btn">
                <i class="far fa-comment"></i>
                <span class="count comment-count" id="comment-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn share-btn" onclick="sharePost(' . $post['id'] . ', \'' . addslashes($post['title']) . '\')">
                <i class="fas fa-share-alt"></i>
              </button>
            </div>
            <a href="#" class="readmore" data-post-id="' . $post['id'] . '">
              <span>Read More</span>
              <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
        ';
    }
}
