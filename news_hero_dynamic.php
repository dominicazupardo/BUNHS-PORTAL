<?php

$query = "SELECT * FROM news ORDER BY created_at DESC LIMIT 3"; // 1 featured + 2 secondary
$result = $conn->query($query);
$news = [];
while ($row = $result->fetch_assoc()) {
    $news[] = $row;
}

if (count($news) == 0) {
    echo '<div class="empty-state"><i class="bi bi-newspaper"></i>No news uploaded yet.</div>';
} else {
    // ── Featured Article ───────────────────────────────────────────────
    $featured = $news[0];
    $image    = !empty($featured['image']) ? "assets/img/blog/" . $featured['image'] : "assets/img/blog/default.webp";
    $date     = date("M d, Y", strtotime($featured['news_date']));

    echo '
    <article class="featured-post mb-4">
      <div class="image-container">
        <img src="' . $image . '" alt="' . htmlspecialchars($featured['title']) . '" loading="lazy">
        <div class="post-overlay">
          <div class="post-meta">
            <span class="category-tag">' . htmlspecialchars($featured['category']) . '</span>
            <span class="date-tag">' . $date . '</span>
          </div>
          <h2 class="post-title mb-2">
            <a href="#">' . htmlspecialchars($featured['title']) . '</a>
          </h2>
          <p class="post-excerpt">' . htmlspecialchars($featured['short_description']) . '</p>
          <div class="post-author">
            <span>by</span>
            <a href="#">' . htmlspecialchars($featured['author']) . '</a>
          </div>
        </div>
      </div>
      <div class="interaction-bar" data-post-id="' . $featured['id'] . '">
        <button class="interaction-btn like-btn" onclick="toggleLike(' . $featured['id'] . ')">
          <i class="far fa-heart"></i>
          <span class="count like-count" id="like-count-' . $featured['id'] . '">0</span>
        </button>
        <button class="interaction-btn comment-btn" onclick="toggleInlineComment(' . $featured['id'] . ')">
          <i class="far fa-comment"></i>
          <span class="count comment-count" id="comment-count-' . $featured['id'] . '">0</span>
        </button>
        <button class="interaction-btn share-btn" onclick="sharePost(' . $featured['id'] . ', \'' . addslashes($featured['title']) . '\')">
          <i class="fas fa-share-alt"></i>
        </button>
      </div>
      <div class="comment-section" id="comment-section-' . $featured['id'] . '" style="display:none;">
        <input type="text" placeholder="Add a comment…" class="comment-input"
               onkeypress="addInlineComment(event, ' . $featured['id'] . ')">
        <div class="comments-list" id="comments-list-' . $featured['id'] . '"></div>
      </div>
    </article>
    ';

    // ── Secondary Articles ─────────────────────────────────────────────
    if (count($news) > 1) {
        echo '<div class="row g-3">';
        for ($i = 1; $i < count($news) && $i < 3; $i++) {
            $item  = $news[$i];
            $image = !empty($item['image']) ? "assets/img/blog/" . $item['image'] : "assets/img/blog/default.webp";
            $date  = date("M d, Y", strtotime($item['news_date']));

            echo '
        <div class="col-md-6">
          <article class="secondary-post">
            <div class="post-image">
              <img src="' . $image . '" alt="' . htmlspecialchars($item['title']) . '" loading="lazy">
            </div>
            <div class="interaction-bar" data-post-id="' . $item['id'] . '">
              <button class="interaction-btn like-btn" onclick="toggleLike(' . $item['id'] . ')">
                <i class="far fa-heart"></i>
                <span class="count like-count" id="like-count-' . $item['id'] . '">0</span>
              </button>
              <button class="interaction-btn comment-btn" onclick="toggleInlineComment(' . $item['id'] . ')">
                <i class="far fa-comment"></i>
                <span class="count comment-count" id="comment-count-' . $item['id'] . '">0</span>
              </button>
              <button class="interaction-btn share-btn" onclick="sharePost(' . $item['id'] . ', \'' . addslashes($item['title']) . '\')">
                <i class="fas fa-share-alt"></i>
              </button>
            </div>
            <div class="comment-section" id="comment-section-' . $item['id'] . '" style="display:none;">
              <input type="text" placeholder="Add a comment…" class="comment-input"
                     onkeypress="addInlineComment(event, ' . $item['id'] . ')">
              <div class="comments-list" id="comments-list-' . $item['id'] . '"></div>
            </div>
            <div class="post-content">
              <div class="post-meta">
                <span class="category-tag" style="background:var(--accent-2);">' . htmlspecialchars($item['category']) . '</span>
                <span class="date-tag" style="color:var(--ink-muted);">' . $date . '</span>
              </div>
              <h3 class="post-title mt-2 mb-1">
                <a href="#">' . htmlspecialchars($item['title']) . '</a>
              </h3>
              <div class="post-author">
                <span>by</span>
                <a href="#">' . htmlspecialchars($item['author']) . '</a>
              </div>
            </div>
          </article>
        </div>
            ';
        }
        echo '</div>';
    }
}
?>

<script>
    /* Inline comment toggle (hero cards) */
    function toggleInlineComment(postId) {
        const section = document.getElementById('comment-section-' + postId);
        if (!section) return;
        const isHidden = section.style.display === 'none' || section.style.display === '';
        section.style.display = isHidden ? 'block' : 'none';
        if (isHidden) section.querySelector('.comment-input').focus();
    }

    function addInlineComment(event, postId) {
        if (event.key !== 'Enter') return;
        const input = event.target;
        const text = input.value.trim();
        if (!text) return;

        const list = document.getElementById('comments-list-' + postId);
        const count = document.getElementById('comment-count-' + postId);
        const item = document.createElement('div');
        item.className = 'comment-item';
        item.textContent = text;
        list.appendChild(item);
        count.textContent = parseInt(count.textContent || 0) + 1;
        input.value = '';
    }
</script>