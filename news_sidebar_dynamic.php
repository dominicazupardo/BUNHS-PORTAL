<?php

// Top Stories — Latest 5
$query_top = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5";
$result_top = $conn->query($query_top);
$top_stories = [];
while ($row = $result_top->fetch_assoc()) {
    $top_stories[] = $row;
}

// Trending — Next 5 (OFFSET 5)
$query_trending = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5 OFFSET 5";
$result_trending = $conn->query($query_trending);
$trending_news = [];
while ($row = $result_trending->fetch_assoc()) {
    $trending_news[] = $row;
}

// More — Next 5 (OFFSET 10)
$query_latest = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5 OFFSET 10";
$result_latest = $conn->query($query_latest);
$latest_news = [];
while ($row = $result_latest->fetch_assoc()) {
    $latest_news[] = $row;
}

/* ── Render helper ───────────────────────────────────── */
function render_tab_article($item, $index = 0)
{
    $image = !empty($item['image']) ? "assets/img/blog/" . $item['image'] : "assets/img/blog/default.webp";
    $date  = date("M d", strtotime($item['news_date']));
    return '
    <article class="tab-post">
      <div class="row g-0 align-items-center">
        <div class="col-4">
          <img src="' . $image . '" alt="' . htmlspecialchars($item['title']) . '" loading="lazy">
        </div>
        <div class="col-8">
          <div class="post-content">
            <span class="category">' . htmlspecialchars($item['category']) . '</span>
            <h4 class="post-title">
              <a href="#">' . htmlspecialchars($item['title']) . '</a>
            </h4>
            <div class="post-author">by <a href="#">' . htmlspecialchars($item['author']) . '</a>
              &nbsp;·&nbsp; <span style="font-family:var(--font-mono);font-size:.62rem;color:var(--ink-muted);">' . $date . '</span>
            </div>
          </div>
        </div>
      </div>
    </article>
    ';
}
?>

<!-- Latest Tab -->
<div class="tab-pane fade show active" id="top-stories" role="tabpanel">
    <?php
    if (count($top_stories) > 0) {
        foreach ($top_stories as $i => $item) {
            echo render_tab_article($item, $i);
        }
    } else {
        echo '<div class="empty-state"><i class="bi bi-newspaper"></i>No news yet.</div>';
    }
    ?>
</div>

<!-- Trending Tab -->
<div class="tab-pane fade" id="trending" role="tabpanel">
    <?php
    if (count($trending_news) > 0) {
        foreach ($trending_news as $i => $item) {
            echo render_tab_article($item, $i);
        }
    } else {
        echo '<div class="empty-state"><i class="bi bi-fire"></i>No trending news yet.</div>';
    }
    ?>
</div>

<!-- More Tab -->
<div class="tab-pane fade" id="latest" role="tabpanel">
    <?php
    if (count($latest_news) > 0) {
        foreach ($latest_news as $i => $item) {
            echo render_tab_article($item, $i);
        }
    } else {
        echo '<div class="empty-state"><i class="bi bi-collection"></i>No more news yet.</div>';
    }
    ?>
</div>